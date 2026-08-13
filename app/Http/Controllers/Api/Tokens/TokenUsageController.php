<?php

namespace App\Http\Controllers\Api\Tokens;

use App\Http\Controllers\Controller;
use App\Services\TokenService;
use App\Services\TokenUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $quote = $usage->quote(
            $tokenType,
            (int) $validated['target_reach'],
            isset($validated['video_duration_seconds'])
                ? (int) $validated['video_duration_seconds']
                : null
        );

        return response()->json([
            'ok' => true,
            'data' => $quote,
        ]);
    }
}
