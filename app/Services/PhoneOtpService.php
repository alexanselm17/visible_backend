<?php

namespace App\Services;

use App\Models\PhoneVerificationOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PhoneOtpService
{
    public function __construct(private readonly TalkSasaSmsService $sms)
    {
    }

    public function sendSignupOtp(User $user): PhoneVerificationOtp
    {
        if ($user->phone_verified_at) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already verified.',
            ]);
        }

        $this->ensureCanSend($user->phone);

        $code = $this->generateCode();
        $otp = PhoneVerificationOtp::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'purpose' => PhoneVerificationOtp::SIGNUP,
            'code_hash' => Hash::make($code),
            'max_attempts' => (int) config('services.talksasa.otp_max_attempts', 5),
            'expires_at' => now()->addMinutes((int) config('services.talksasa.otp_ttl_minutes', 10)),
        ]);

        try {
            $reference = $this->sms->send(
                $user->phone,
                $this->signupMessage($code)
            );
        } catch (\Throwable $exception) {
            $otp->delete();
            throw $exception;
        }

        $otp->update([
            'sent_at' => now(),
            'delivery_reference' => $reference,
        ]);

        return $otp->fresh();
    }

    public function resendSignupOtp(string $phone): PhoneVerificationOtp
    {
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => 'No account was found with this phone number.',
            ]);
        }

        return $this->sendSignupOtp($user);
    }

    public function verifySignupOtp(string $phone, string $code): User
    {
        $otp = PhoneVerificationOtp::where('phone', $phone)
            ->where('purpose', PhoneVerificationOtp::SIGNUP)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages([
                'otp' => 'The OTP is invalid or has expired.',
            ]);
        }

        if ($otp->attempts >= $otp->max_attempts) {
            throw ValidationException::withMessages([
                'otp' => 'Too many failed OTP attempts. Please request a new code.',
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => 'Invalid OTP code.',
            ]);
        }

        return DB::transaction(function () use ($otp) {
            $otp->update(['verified_at' => now()]);

            $user = User::lockForUpdate()->findOrFail($otp->user_id);
            $user->phone_verified_at = now();

            if (Schema::hasColumn('users', 'is_verified')) {
                $user->is_verified = true;
            }

            $user->save();

            return $user->fresh();
        });
    }

    private function ensureCanSend(string $phone): void
    {
        $cooldownSeconds = (int) config('services.talksasa.otp_resend_cooldown_seconds', 60);

        $lastOtp = PhoneVerificationOtp::where('phone', $phone)
            ->where('purpose', PhoneVerificationOtp::SIGNUP)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if ($lastOtp && $lastOtp->sent_at && $lastOtp->sent_at->gt(now()->subSeconds($cooldownSeconds))) {
            throw ValidationException::withMessages([
                'phone' => "Please wait {$cooldownSeconds} seconds before requesting another OTP.",
            ]);
        }
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function signupMessage(string $code): string
    {
        $brand = trim((string) config('app.name', 'Visible DM')) ?: 'Visible DM';

        return "{$brand}: Your verification code is {$code}. Do not share this code.";
    }
}
