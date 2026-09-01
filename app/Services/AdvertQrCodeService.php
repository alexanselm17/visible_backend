<?php

namespace App\Services;

use App\Models\AdvertImages;
use App\Models\AdvertQrCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdvertQrCodeService
{
    private const TOKEN_PATTERN = '/^[A-Za-z0-9_-]{22}$/';

    public function issue(User $user, AdvertImages $advert): string
    {
        $identifier = trim((string) $user->my_code);

        if (! preg_match('/^\d{10}$/', $identifier)) {
            throw ValidationException::withMessages([
                'identifier' => 'Your account must have a valid 10-digit QR identifier.',
            ]);
        }

        $token = $this->tokenFor($identifier, (string) $advert->id);

        AdvertQrCode::updateOrCreate(
            [
                'user_id' => $user->id,
                'advert_id' => $advert->id,
            ],
            [
                'identifier_snapshot' => $identifier,
                'token_hash' => hash('sha256', $token),
                'generated_at' => now(),
            ]
        );

        return $this->publicUrl($token);
    }

    public function resolve(string $decodedQrContent): ?AdvertQrCode
    {
        $token = $this->tokenFromUrl($decodedQrContent);

        if ($token === null) {
            return null;
        }

        return AdvertQrCode::where('token_hash', hash('sha256', $token))->first();
    }

    public function verify(
        string $decodedQrContent,
        User $user,
        AdvertImages $advert
    ): ?AdvertQrCode {
        return DB::transaction(function () use ($decodedQrContent, $user, $advert) {
            $record = $this->resolve($decodedQrContent);
            $identifier = trim((string) $user->my_code);

            if (
                ! $record
                || ! hash_equals((string) $record->user_id, (string) $user->id)
                || ! hash_equals((string) $record->advert_id, (string) $advert->id)
                || ! hash_equals((string) $record->identifier_snapshot, $identifier)
            ) {
                return null;
            }

            $expectedToken = $this->tokenFor($identifier, (string) $advert->id);
            if (! hash_equals((string) $record->token_hash, hash('sha256', $expectedToken))) {
                return null;
            }

            $record->last_verified_at = now();
            $record->save();

            return $record;
        });
    }

    private function tokenFor(string $identifier, string $advertId): string
    {
        $key = $this->signingKey();
        $binaryHash = substr(
            hash_hmac('sha256', $identifier.'|'.strtolower($advertId), $key, true),
            0,
            16
        );

        return rtrim(strtr(base64_encode($binaryHash), '+/', '-_'), '=');
    }

    private function tokenFromUrl(string $decodedQrContent): ?string
    {
        $decodedQrContent = trim($decodedQrContent);
        $parts = parse_url($decodedQrContent);
        $expected = parse_url($this->baseUrl());

        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || strtolower((string) $parts['scheme']) !== strtolower((string) ($expected['scheme'] ?? 'https'))
            || strtolower((string) $parts['host']) !== strtolower((string) ($expected['host'] ?? ''))
        ) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $token = is_string($query['qr'] ?? null) ? $query['qr'] : null;

        return $token && preg_match(self::TOKEN_PATTERN, $token) ? $token : null;
    }

    private function publicUrl(string $token): string
    {
        $baseUrl = $this->baseUrl();
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query(['qr' => $token], '', '&', PHP_QUERY_RFC3986);
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) config('services.visible_qr.public_url', 'https://www.visibledm.com'), '/');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('VISIBLE_QR_PUBLIC_URL must be a valid URL.');
        }

        return $url;
    }

    private function signingKey(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                $key = $decoded;
            }
        }

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required to sign advert QR codes.');
        }

        return $key;
    }
}
