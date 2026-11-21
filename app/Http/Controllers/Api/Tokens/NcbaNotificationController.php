<?php

namespace App\Http\Controllers\Api\Tokens;

use App\Http\Controllers\Controller;
use App\Services\NcbaNotificationVerifier;
use App\Services\NcbaPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class NcbaNotificationController extends Controller
{
    public function __invoke(Request $request, NcbaNotificationVerifier $verifier, NcbaPaymentService $payments): JsonResponse
    {
        try {
            if (! $verifier->ready()) {
                throw new HttpException(503, 'Payment notifications are not configured.');
            }
            if (config('ncba.require_https') && ! $request->secure()) {
                throw new HttpException(403, 'HTTPS is required.');
            }
            $allowedIps = config('ncba.allowed_ips', []);
            if ($allowedIps && ! IpUtils::checkIp($request->ip(), $allowedIps)) {
                throw new HttpException(403, 'Notification source is not allowed.');
            }
            if (strtolower(trim(explode(';', $request->header('Content-Type', ''))[0])) !== 'application/json') {
                throw new HttpException(415, 'Only application/json is supported.');
            }
            $body = $request->getContent();
            if (strlen($body) > (int) config('ncba.max_body_bytes')) {
                throw new HttpException(413, 'Notification is too large.');
            }
            // Global TrimStrings must not change the bank's signed strings.
            $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || array_is_list($payload)) {
                throw new HttpException(422, 'A JSON object is required.');
            }
            $verified = $verifier->verify($payload);
            if (! $payments->receive($verified)) {
                throw new HttpException(409, 'Conflicting receipt. Contact reconciliation support.');
            }

            return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Notification received.']);
        } catch (JsonException $exception) {
            Log::warning('NCBA: malformed JSON.', [
                'error_code' => $exception->getCode(),
            ]);

            return response()->json([
                'ResultCode' => '1',
                'ResultDesc' => 'Invalid notification format.',
            ], 422);
        } catch (ValidationException $exception) {
            Log::warning('NCBA: notification validation failed.', [
                'invalid_fields' => array_keys($exception->errors()),
            ]);

            return response()->json([
                'ResultCode' => '1',
                'ResultDesc' => 'Invalid notification format.',
            ], 422);
        } catch (HttpException $exception) {
            return response()->json(['ResultCode' => '1', 'ResultDesc' => $exception->getMessage()], $exception->getStatusCode());
        } catch (Throwable $exception) {
            Log::error('NCBA notification could not be committed.', ['exception_type' => $exception::class]);

            return response()->json(['ResultCode' => '1', 'ResultDesc' => 'Temporarily unavailable. Please retry.'], 503);
        }
    }
}
