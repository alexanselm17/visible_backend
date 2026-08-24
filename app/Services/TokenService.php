<?php

namespace App\Services;

use App\Models\AdvertSubmission;
use App\Models\TokenHold;
use App\Models\TokenPurchase;
use App\Models\TokenTransaction;
use App\Models\TokenType;
use App\Models\TokenWallet;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TokenService
{
    public function getTokenTypes(bool $activeOnly = false): Collection
    {
        return TokenType::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->get();
    }

    public function getTypeByCode(string $code): TokenType
    {
        return TokenType::where('code', strtoupper($code))->firstOrFail();
    }

    public function getTypeForMedia(string $mediaType): TokenType
    {
        return TokenType::where('media_type', strtoupper($mediaType))
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function requiredTokens(TokenType $tokenType, ?int $durationSeconds = null): int
    {
        if ($tokenType->media_type !== TokenType::VIDEO) {
            return 1;
        }

        if (!$durationSeconds || $durationSeconds < 1) {
            throw ValidationException::withMessages([
                'video_duration_seconds' => 'Video duration is required for a video campaign.',
            ]);
        }

        if ($tokenType->max_video_duration_seconds
            && $durationSeconds > $tokenType->max_video_duration_seconds) {
            throw ValidationException::withMessages([
                'video_duration_seconds' => "Video duration cannot exceed {$tokenType->max_video_duration_seconds} seconds.",
            ]);
        }

        $secondsPerToken = (int) $tokenType->seconds_per_token;
        if ($secondsPerToken < 1) {
            throw new RuntimeException('Gold token duration pricing is not configured.');
        }

        return (int) ceil($durationSeconds / $secondsPerToken);
    }

    public function quote(string $code, int $quantity): array
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        $type = $this->getTypeByCode($code);

        if (!$type->is_active) {
            throw ValidationException::withMessages([
                'token_type' => 'This token type is currently unavailable.',
            ]);
        }

        if ($type->unit_price === null || (float) $type->unit_price <= 0) {
            throw ValidationException::withMessages([
                'token_type' => 'Pricing has not been configured for this token type.',
            ]);
        }

        $unitPrice = (float) $type->unit_price;

        return [
            'token_type' => $type->code,
            'name' => $type->name,
            'media_type' => $type->media_type,
            'quantity' => $quantity,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'total_amount' => number_format($unitPrice * $quantity, 2, '.', ''),
            'currency' => $type->currency,
        ];
    }

    public function walletSummary(string $userId): array
    {
        $types = $this->getTokenTypes();

        $balances = $types->map(function (TokenType $type) use ($userId) {
            $wallet = TokenWallet::firstOrCreate(
                [
                    'user_id' => $userId,
                    'token_type_id' => $type->id,
                ],
                [
                    'balance' => 0,
                    'locked_balance' => 0,
                ]
            );

            return [
                'token_type' => $type->code,
                'name' => $type->name,
                'media_type' => $type->media_type,
                'balance' => (int) $wallet->balance,
                'locked_balance' => (int) $wallet->locked_balance,
                'total_balance' => (int) $wallet->total_balance,
                'remaining_tokens' => (int) $wallet->balance,
                'reserved_tokens' => (int) $wallet->locked_balance,
                'total_tokens' => (int) $wallet->total_balance,
                'unit_price' => $type->unit_price,
                'currency' => $type->currency,
                'seconds_per_token' => $type->seconds_per_token,
                'max_video_duration_seconds' => $type->max_video_duration_seconds,
                'is_active' => (bool) $type->is_active,
                'purchasable' => (bool) ($type->is_active && $type->unit_price !== null && (float) $type->unit_price > 0),
            ];
        })->values()->all();

        return [
            'user_id' => $userId,
            'balances' => $balances,
        ];
    }

    public function createPurchase(string $userId, string $code, int $quantity): TokenPurchase
    {
        $quote = $this->quote($code, $quantity);
        $type = $this->getTypeByCode($code);

        return TokenPurchase::create([
            'user_id' => $userId,
            'token_type_id' => $type->id,
            'quantity' => $quantity,
            'unit_price' => $quote['unit_price'],
            'total_amount' => $quote['total_amount'],
            'currency' => $quote['currency'],
            'status' => TokenPurchase::PENDING,
        ])->load('tokenType');
    }

    public function confirmPurchase(string $purchaseId, string $paymentReference): TokenPurchase
    {
        return DB::transaction(function () use ($purchaseId, $paymentReference) {
            $purchase = TokenPurchase::lockForUpdate()->findOrFail($purchaseId);

            if ($purchase->status === TokenPurchase::PAID) {
                if ($purchase->payment_reference !== $paymentReference) {
                    throw ValidationException::withMessages([
                        'payment_reference' => 'This purchase has already been paid using another payment reference.',
                    ]);
                }

                return $purchase->load('tokenType');
            }

            if ($purchase->status !== TokenPurchase::PENDING) {
                throw ValidationException::withMessages([
                    'purchase' => "Only PENDING purchases can be confirmed. Current status: {$purchase->status}.",
                ]);
            }

            $referenceExists = TokenPurchase::where('payment_reference', $paymentReference)
                ->where('id', '!=', $purchase->id)
                ->exists();

            if ($referenceExists) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'This payment reference has already been used.',
                ]);
            }

            $wallet = $this->lockedWallet($purchase->user_id, $purchase->token_type_id);
            $wallet->balance += (int) $purchase->quantity;
            $wallet->save();

            $purchase->status = TokenPurchase::PAID;
            $purchase->payment_reference = $paymentReference;
            $purchase->paid_at = now();
            $purchase->save();

            TokenTransaction::create([
                'token_wallet_id' => $wallet->id,
                'type' => TokenTransaction::PURCHASE,
                'amount' => (int) $purchase->quantity,
                'description' => "Purchased {$purchase->quantity} {$purchase->tokenType->code} token(s)",
                'reference_id' => $purchase->id,
                'reference_type' => TokenPurchase::class,
                'metadata' => [
                    'payment_reference' => $paymentReference,
                    'unit_price' => $purchase->unit_price,
                    'total_amount' => $purchase->total_amount,
                    'currency' => $purchase->currency,
                ],
            ]);

            return $purchase->fresh('tokenType');
        });
    }

    public function cancelPurchase(string $purchaseId, string $userId): TokenPurchase
    {
        return DB::transaction(function () use ($purchaseId, $userId) {
            $purchase = TokenPurchase::where('user_id', $userId)
                ->lockForUpdate()
                ->findOrFail($purchaseId);

            if ($purchase->status !== TokenPurchase::PENDING) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only a PENDING purchase can be cancelled.',
                ]);
            }

            $purchase->status = TokenPurchase::CANCELLED;
            $purchase->save();

            return $purchase->fresh('tokenType');
        });
    }

    public function reserveForSubmission(
        string $userId,
        AdvertSubmission $submission,
        TokenType $tokenType,
        int $amount
    ): TokenHold {
        if ($amount < 1) {
            throw new RuntimeException('At least one token must be reserved.');
        }

        return DB::transaction(function () use ($userId, $submission, $tokenType, $amount) {
            $existingHold = TokenHold::where('advert_submission_id', $submission->id)
                ->lockForUpdate()
                ->first();

            if ($existingHold) {
                return $existingHold;
            }

            $wallet = $this->lockedWallet($userId, $tokenType->id);

            if ((int) $wallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'tokens' => "Insufficient {$tokenType->code} token balance. {$amount} token(s) required, {$wallet->balance} available.",
                ]);
            }

            $wallet->balance -= $amount;
            $wallet->locked_balance += $amount;
            $wallet->save();

            $hold = TokenHold::create([
                'token_wallet_id' => $wallet->id,
                'advert_submission_id' => $submission->id,
                'amount_locked' => $amount,
                'status' => TokenHold::ACTIVE,
            ]);

            TokenTransaction::create([
                'token_wallet_id' => $wallet->id,
                'type' => TokenTransaction::HOLD,
                'amount' => $amount,
                'description' => "Reserved {$amount} {$tokenType->code} token(s) for advert submission",
                'reference_id' => $submission->id,
                'reference_type' => AdvertSubmission::class,
            ]);

            return $hold;
        });
    }

    public function settleSubmission(AdvertSubmission $submission, int $actualTokens): ?TokenHold
    {
        if ($actualTokens < 1) {
            throw new RuntimeException('At least one token must be spent.');
        }

        return DB::transaction(function () use ($submission, $actualTokens) {
            $hold = TokenHold::where('advert_submission_id', $submission->id)
                ->lockForUpdate()
                ->first();

            if (!$hold) {
                throw new RuntimeException('No token reservation exists for this submission.');
            }

            if ($hold->status !== TokenHold::ACTIVE) {
                return $hold;
            }

            $wallet = TokenWallet::whereKey($hold->token_wallet_id)->lockForUpdate()->firstOrFail();
            $reserved = (int) $hold->amount_locked;
            $extraRequired = max(0, $actualTokens - $reserved);
            $released = max(0, $reserved - $actualTokens);

            if ($extraRequired > 0 && (int) $wallet->balance < $extraRequired) {
                $tokenCode = optional($wallet->tokenType)->code ?? 'required';
                throw ValidationException::withMessages([
                    'tokens' => "The final media requires {$actualTokens} {$tokenCode} token(s). Add {$extraRequired} more token(s) before rollout.",
                ]);
            }

            $wallet->locked_balance -= $reserved;

            if ($extraRequired > 0) {
                $wallet->balance -= $extraRequired;
            }

            if ($released > 0) {
                $wallet->balance += $released;
            }

            $wallet->save();

            TokenTransaction::create([
                'token_wallet_id' => $wallet->id,
                'type' => TokenTransaction::SPEND,
                'amount' => $actualTokens,
                'description' => 'Media tokens consumed when advert was published',
                'reference_id' => $submission->id,
                'reference_type' => AdvertSubmission::class,
            ]);

            if ($released > 0) {
                TokenTransaction::create([
                    'token_wallet_id' => $wallet->id,
                    'type' => TokenTransaction::RELEASE,
                    'amount' => $released,
                    'description' => 'Unused reserved media tokens released after final pricing',
                    'reference_id' => $submission->id,
                    'reference_type' => AdvertSubmission::class,
                ]);
            }

            $hold->amount_spent = $actualTokens;
            $hold->amount_released = $released;
            $hold->status = $released > 0 ? TokenHold::PARTIALLY_SETTLED : TokenHold::SETTLED;
            $hold->save();

            $submission->tokens_spent = $actualTokens;
            $submission->saveQuietly();

            return $hold;
        });
    }

    public function releaseSubmission(AdvertSubmission $submission, string $reason = 'Submission rejected'): ?TokenHold
    {
        return DB::transaction(function () use ($submission, $reason) {
            $hold = TokenHold::where('advert_submission_id', $submission->id)
                ->lockForUpdate()
                ->first();

            if (!$hold || $hold->status !== TokenHold::ACTIVE) {
                return $hold;
            }

            $wallet = TokenWallet::whereKey($hold->token_wallet_id)->lockForUpdate()->firstOrFail();
            $amount = (int) $hold->amount_locked;

            $wallet->locked_balance -= $amount;
            $wallet->balance += $amount;
            $wallet->save();

            $hold->amount_released = $amount;
            $hold->status = TokenHold::CANCELLED;
            $hold->save();

            TokenTransaction::create([
                'token_wallet_id' => $wallet->id,
                'type' => TokenTransaction::RELEASE,
                'amount' => $amount,
                'description' => $reason,
                'reference_id' => $submission->id,
                'reference_type' => AdvertSubmission::class,
            ]);

            $submission->tokens_refunded_at = now();
            $submission->saveQuietly();

            return $hold;
        });
    }

    public function adjustBalance(
        string $userId,
        string $code,
        int $amount,
        string $reason,
        ?string $referenceId = null
    ): TokenWallet {
        if ($amount === 0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount cannot be zero.',
            ]);
        }

        $type = $this->getTypeByCode($code);

        return DB::transaction(function () use ($userId, $type, $amount, $reason, $referenceId) {
            $wallet = $this->lockedWallet($userId, $type->id);

            if ($amount < 0 && (int) $wallet->balance < abs($amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'The adjustment would make the available token balance negative.',
                ]);
            }

            $wallet->balance += $amount;
            $wallet->save();

            TokenTransaction::create([
                'token_wallet_id' => $wallet->id,
                'type' => TokenTransaction::ADJUSTMENT,
                'amount' => abs($amount),
                'description' => $reason,
                'reference_id' => $referenceId,
                'reference_type' => 'ADMIN_ADJUSTMENT',
                'metadata' => ['direction' => $amount > 0 ? 'CREDIT' : 'DEBIT'],
            ]);

            return $wallet->fresh('tokenType');
        });
    }

    public function userTransactions(string $userId, int $perPage = 20): LengthAwarePaginator
    {
        $walletIds = TokenWallet::where('user_id', $userId)->pluck('id');

        return TokenTransaction::query()
            ->whereIn('token_wallet_id', $walletIds)
            ->with('wallet.tokenType')
            ->latest()
            ->paginate($perPage);
    }

    private function lockedWallet(string $userId, string $tokenTypeId): TokenWallet
    {
        $wallet = TokenWallet::firstOrCreate(
            [
                'user_id' => $userId,
                'token_type_id' => $tokenTypeId,
            ],
            [
                'balance' => 0,
                'locked_balance' => 0,
            ]
        );

        return TokenWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }
}
