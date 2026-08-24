<?php

namespace App\Http\Controllers\Api\Tokens;

use App\Http\Controllers\Controller;
use App\Models\TokenPurchase;
use App\Models\TokenType;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    public function types(TokenService $tokenService): JsonResponse
    {
        $types = $tokenService->getTokenTypes()->map(function (TokenType $type) {
            return [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'media_type' => $type->media_type,
                'unit_price' => $type->unit_price,
                'currency' => $type->currency,
                'seconds_per_token' => $type->seconds_per_token,
                'max_video_duration_seconds' => $type->max_video_duration_seconds,
                'description' => $type->description,
                'is_active' => (bool) $type->is_active,
                'purchasable' => (bool) ($type->is_active && $type->unit_price !== null && (float) $type->unit_price > 0),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'types' => $types,
            ],
        ]);
    }

    public function quote(Request $request, TokenService $tokenService): JsonResponse
    {
        $validated = $request->validate([
            'token_type' => ['required', 'string', 'in:GOLD,PLATINUM,SILVER'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        return response()->json([
            'ok' => true,
            'data' => $tokenService->quote($validated['token_type'], (int) $validated['quantity']),
        ]);
    }

    public function wallet(Request $request, TokenService $tokenService): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $tokenService->walletSummary($request->user()->id),
        ]);
    }

    public function campaignOwnerWallet(
        Request $request,
        TokenService $tokenService,
        string $userId
    ): JsonResponse {
        if ((string) $request->user()->id !== $userId) {
            return response()->json([
                'ok' => false,
                'message' => 'You are not authorized to view this campaign owner wallet.',
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'data' => $tokenService->walletSummary($userId),
        ]);
    }

    public function transactions(Request $request, TokenService $tokenService): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $transactions = $tokenService->userTransactions($request->user()->id, $perPage);

        return response()->json([
            'ok' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function purchases(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $purchases = TokenPurchase::where('user_id', $request->user()->id)
            ->with('tokenType')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $purchases->items(),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    public function purchase(Request $request, TokenService $tokenService): JsonResponse
    {
        $validated = $request->validate([
            'token_type' => ['required', 'string', 'in:GOLD,PLATINUM,SILVER'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $purchase = $tokenService->createPurchase(
            $request->user()->id,
            $validated['token_type'],
            (int) $validated['quantity']
        );

        return response()->json([
            'ok' => true,
            'message' => 'Token purchase created. Credit the wallet only after payment is confirmed.',
            'data' => $purchase,
        ], 201);
    }

    public function cancelPurchase(Request $request, string $purchaseId, TokenService $tokenService): JsonResponse
    {
        $purchase = $tokenService->cancelPurchase($purchaseId, $request->user()->id);

        return response()->json([
            'ok' => true,
            'message' => 'Token purchase cancelled.',
            'data' => $purchase,
        ]);
    }

    public function confirmPurchase(
        Request $request,
        string $purchaseId,
        TokenService $tokenService
    ): JsonResponse {
        $this->ensureTokenAdmin($request);

        $validated = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
        ]);

        $purchase = $tokenService->confirmPurchase($purchaseId, $validated['payment_reference']);

        return response()->json([
            'ok' => true,
            'message' => 'Payment confirmed and tokens credited successfully.',
            'data' => $purchase,
        ]);
    }

    public function updateType(
        Request $request,
        string $code,
        TokenService $tokenService
    ): JsonResponse {
        $this->ensureTokenAdmin($request);

        $validated = $request->validate([
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'seconds_per_token' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_video_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $type = DB::transaction(function () use ($code, $validated, $tokenService) {
            $type = $tokenService->getTypeByCode($code);

            if (isset($validated['currency'])) {
                $validated['currency'] = strtoupper($validated['currency']);
            }

            if ($type->media_type !== TokenType::VIDEO) {
                unset($validated['seconds_per_token'], $validated['max_video_duration_seconds']);
            }

            $type->fill($validated);
            $type->save();

            if ($type->media_type === TokenType::VIDEO && (int) $type->seconds_per_token < 1) {
                throw ValidationException::withMessages([
                    'seconds_per_token' => 'Gold tokens must define how many video seconds one token covers.',
                ]);
            }

            $this->validatePriceHierarchy();

            return $type->fresh();
        });

        return response()->json([
            'ok' => true,
            'message' => 'Token configuration updated.',
            'data' => $type,
        ]);
    }

    public function adjustWallet(Request $request, TokenService $tokenService): JsonResponse
    {
        $this->ensureTokenAdmin($request);

        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'token_type' => ['required', 'string', 'in:GOLD,PLATINUM,SILVER'],
            'amount' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $wallet = $tokenService->adjustBalance(
            $validated['user_id'],
            $validated['token_type'],
            (int) $validated['amount'],
            $validated['reason']
        );

        return response()->json([
            'ok' => true,
            'message' => 'Token wallet adjusted successfully.',
            'data' => $wallet,
        ]);
    }

    private function ensureTokenAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || (!$user->isAdmin() && !$user->isDeveloper())) {
            abort(403, 'You are not authorized to manage token pricing or payments.');
        }
    }

    private function validatePriceHierarchy(): void
    {
        $prices = TokenType::whereIn('code', [
            TokenType::GOLD,
            TokenType::PLATINUM,
            TokenType::SILVER,
        ])->pluck('unit_price', 'code');

        if ($prices->count() !== 3 || $prices->contains(fn ($price) => $price === null)) {
            return;
        }

        $gold = (float) $prices[TokenType::GOLD];
        $platinum = (float) $prices[TokenType::PLATINUM];
        $silver = (float) $prices[TokenType::SILVER];

        if (!($gold > $platinum && $platinum > $silver)) {
            throw ValidationException::withMessages([
                'unit_price' => 'Token pricing must remain Gold > Platinum > Silver.',
            ]);
        }
    }
}
