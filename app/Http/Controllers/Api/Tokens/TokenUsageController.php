<?php

namespace App\Http\Controllers\Api\Tokens;

use App\Http\Controllers\Controller;
use App\Models\TokenType;
use App\Services\TokenService;
use App\Services\TokenUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TokenUsageController extends Controller
{
    public function quote(
        Request $request,
        TokenService $tokens,
        TokenUsageService $usage
    ): JsonResponse {
        if ($request->filled('type')) {
            $request->merge([
                'type' => strtoupper((string) $request->input('type')),
            ]);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:VIDEO,IMAGE,TEXT'],
            'target_reach' => ['required', 'integer', 'min:1', 'max:1000000'],
            'video_duration_seconds' => ['required_if:type,VIDEO', 'nullable', 'integer', 'min:1'],
        ]);

        $tokenType = $tokens->getTypeForMedia($validated['type']);

        return response()->json([
            'ok' => true,
            'data' => $usage->quote(
                $tokenType,
                (int) $validated['target_reach'],
                isset($validated['video_duration_seconds'])
                    ? (int) $validated['video_duration_seconds']
                    : null
            ),
        ]);
    }

    public function rules(TokenService $tokens): JsonResponse
    {
        $types = $tokens->getTokenTypes()->map(fn (TokenType $type) => [
            'code' => $type->code,
            'name' => $type->name,
            'media_type' => $type->media_type,
            'people_per_token' => (int) $type->people_per_token,
            'seconds_per_token' => $type->seconds_per_token,
            'max_video_duration_seconds' => $type->max_video_duration_seconds,
            'is_active' => (bool) $type->is_active,
        ])->values();

        return response()->json([
            'ok' => true,
            'data' => ['rules' => $types],
        ]);
    }

    public function updateRules(
        Request $request,
        string $code,
        TokenService $tokens
    ): JsonResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'people_per_token' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'seconds_per_token' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_video_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $type = $tokens->getTypeByCode($code);

        if ($type->media_type !== TokenType::VIDEO) {
            unset(
                $validated['seconds_per_token'],
                $validated['max_video_duration_seconds']
            );
        }

        $type->fill($validated);
        $type->save();

        if ((int) $type->people_per_token < 1) {
            throw ValidationException::withMessages([
                'people_per_token' => 'People per token must be at least 1.',
            ]);
        }

        if (
            $type->media_type === TokenType::VIDEO
            && (int) $type->seconds_per_token < 1
        ) {
            throw ValidationException::withMessages([
                'seconds_per_token' => 'Gold must define how many video seconds each media unit covers.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Token usage rules updated successfully.',
            'data' => [
                'code' => $type->code,
                'media_type' => $type->media_type,
                'people_per_token' => (int) $type->people_per_token,
                'seconds_per_token' => $type->seconds_per_token,
                'max_video_duration_seconds' => $type->max_video_duration_seconds,
            ],
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || (!$user->isAdmin() && !$user->isDeveloper())) {
            abort(403, 'You are not authorized to manage token usage rules.');
        }
    }
}
