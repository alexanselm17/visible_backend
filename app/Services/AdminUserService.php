<?php

namespace App\Services;

use App\Models\PhoneVerificationOtp;
use App\Models\User;
use App\Models\UserAccountEvent;
use App\Support\AdminAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public static function canManage(User $user, bool $sensitive = false): bool
    {
        return AdminAccess::allows('users') && (
            AdminAccess::allows('settings') || (! $sensitive && ! $user->hasRole(['admin', 'dev', 'manager']))
        );
    }

    public function create(array $data): User
    {
        abort_unless(AdminAccess::allows('settings'), 403);

        return DB::transaction(function () use ($data) {
            do {
                $identifier = (string) random_int(1000000000, 9999999999);
            } while (User::withTrashed()->where('my_code', $identifier)->exists());

            $user = User::create($this->profileFields($data) + ['my_code' => $identifier, 'is_verified' => false]);
            $user->permissions()->sync($data['permissions'] ?? []);
            $this->record($user, 'account_created');

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user = $this->locked($user);
            $fields = $this->profileFields($data);
            if (! AdminAccess::allows('settings')) {
                unset($fields['role_id']);
            }
            if (array_key_exists('is_active', $fields) && ! $fields['is_active'] && $user->id === auth()->id()) {
                throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own staff account.']);
            }
            $phoneChanged = isset($fields['phone']) && $fields['phone'] !== $user->phone;
            $emailChanged = isset($fields['email']) && $fields['email'] !== $user->email;
            $passwordChanged = filled($fields['password'] ?? null);
            if (! $passwordChanged) {
                unset($fields['password']);
            }
            if ($phoneChanged) {
                $fields['phone_verified_at'] = null;
                $this->expirePendingOtps($user);
            }
            if ($emailChanged) {
                $fields['email_verified_at'] = null;
            }
            $user->forceFill($fields);
            $changed = array_keys($user->getDirty());
            $user->save();
            if (AdminAccess::allows('settings') && array_key_exists('permissions', $data)) {
                $changes = $user->permissions()->sync($data['permissions'] ?? []);
                if (collect($changes)->flatten()->isNotEmpty()) {
                    $changed[] = 'permissions';
                }
            }
            if (! $user->is_active || $phoneChanged || $passwordChanged) {
                $this->clearSessions($user);
            }
            $this->record($user, 'profile_updated', metadata: ['changed_fields' => $changed]);

            return $user->refresh();
        });
    }

    public function setActive(User $user, bool $active, ?string $reason = null): void
    {
        DB::transaction(function () use ($user, $active, $reason) {
            $user = $this->locked($user);
            if (! $active && $user->id === auth()->id()) {
                throw ValidationException::withMessages(['user' => 'You cannot deactivate your own staff account.']);
            }
            $user->update(['is_active' => $active]);
            if (! $active) {
                $this->clearSessions($user);
            }
            $this->record($user, $active ? 'account_activated' : 'account_deactivated', $reason);
        });
    }

    public function setVerification(User $user, string $kind, bool $verified, string $reason): void
    {
        $this->requireReason($reason);
        DB::transaction(function () use ($user, $kind, $verified, $reason) {
            $user = $this->locked($user, sensitive: true);
            $field = match ($kind) {
                'account' => 'is_verified', 'phone' => 'phone_verified_at', 'email' => 'email_verified_at',
                default => throw new \InvalidArgumentException('Unsupported verification type.'),
            };
            if ($verified && $kind !== 'account' && blank($user->{$kind})) {
                throw ValidationException::withMessages([$kind => "Add a {$kind} before marking it verified."]);
            }
            $user->forceFill([$field => $kind === 'account' ? $verified : ($verified ? now() : null)])->save();
            if ($kind === 'phone') {
                $this->expirePendingOtps($user);
                if (! $verified) {
                    $this->clearSessions($user);
                }
            }
            $this->record($user, $kind.($verified ? '_verified' : '_verification_removed'), $reason, ['method' => 'staff_review']);
        });
    }

    public function sendOtp(User $user): void
    {
        $this->authorize($user);
        $user->refresh();
        try {
            app(PhoneOtpService::class)->sendSignupOtp($user);
        } catch (\App\Exceptions\SmsDeliveryException $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }
        $this->record($user, 'phone_otp_sent');
    }

    public function verifyPhoneOtp(User $user, string $code): void
    {
        $this->authorize($user, sensitive: true);
        $user->refresh();
        if ($user->phone_verified_at) {
            throw ValidationException::withMessages(['otp' => 'This phone is already verified.']);
        }
        app(PhoneOtpService::class)->verifySignupOtp($user->phone, $code);
        $this->record($user, 'phone_verified', metadata: ['method' => 'otp', 'also_verified_account' => true]);
    }

    public function revokeSessions(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = $this->locked($user);
            $counts = $this->clearSessions($user);
            $this->record($user, 'sessions_revoked', metadata: $counts);
        });
    }

    public function revokeApiToken(User $user, string $tokenId): void
    {
        DB::transaction(function () use ($user, $tokenId) {
            $user = $this->locked($user);
            $count = $user->tokens()->whereKey($tokenId)->delete();
            if (! $count) {
                throw ValidationException::withMessages(['token' => 'The session no longer exists for this user.']);
            }
            $this->record($user, 'api_session_revoked', metadata: ['token_id' => $tokenId]);
        });
    }

    public function delete(User $user, string $reason): void
    {
        $this->requireReason($reason);
        DB::transaction(function () use ($user, $reason) {
            $user = $this->locked($user, sensitive: true);
            if ($user->id === auth()->id()) {
                throw ValidationException::withMessages(['user' => 'You cannot delete your own staff account.']);
            }
            $user->is_active = false;
            $this->clearSessions($user);
            $this->expirePendingOtps($user);
            $user->save();
            $this->record($user, 'account_deleted', $reason);
            $user->delete();
        });
    }

    public function restore(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = $this->locked($user, sensitive: true, allowDeleted: true);
            if (! $user->trashed()) {
                throw ValidationException::withMessages(['user' => 'Only deleted accounts can be restored.']);
            }
            $user->restore();
            $user->update(['is_active' => false]);
            $this->record($user, 'account_restored', 'Restored as inactive; activation is a separate action.');
        });
    }

    public function bulk(iterable $users, string $action, ?string $reason = null, ?string $roleId = null): void
    {
        DB::transaction(function () use ($users, $action, $reason, $roleId) {
            foreach ($users as $user) {
                match ($action) {
                    'activate' => $this->setActive($user, true, $reason),
                    'deactivate' => $this->setActive($user, false, $reason),
                    'verify' => $this->setVerification($user, 'account', true, (string) $reason),
                    'unverify' => $this->setVerification($user, 'account', false, (string) $reason),
                    'delete' => $this->delete($user, (string) $reason),
                    'restore' => $this->restore($user),
                    'role' => $this->assignRole($user, $roleId),
                    default => throw new \InvalidArgumentException('Unsupported user action.'),
                };
            }
        });
    }

    private function assignRole(User $user, ?string $roleId): void
    {
        abort_unless(AdminAccess::allows('settings'), 403);
        if ($roleId && ! \App\Models\RolesModel::whereKey($roleId)->exists()) {
            throw ValidationException::withMessages(['role_id' => 'Choose an existing role.']);
        }
        $this->update($user, ['role_id' => $roleId]);
    }

    private function locked(User $user, bool $sensitive = false, bool $allowDeleted = false): User
    {
        $user = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
        $this->authorize($user, $sensitive, $allowDeleted);

        return $user;
    }

    private function authorize(User $user, bool $sensitive = false, bool $allowDeleted = false): void
    {
        abort_unless(static::canManage($user, $sensitive), 403);
        if (! $allowDeleted && $user->trashed()) {
            throw ValidationException::withMessages(['user' => 'Restore this deleted account before changing it.']);
        }
    }

    private function profileFields(array $data): array
    {
        return Arr::only($data, [
            'fullname', 'username', 'email', 'phone', 'occupation', 'gender', 'county_id',
            'subcounty_id', 'referal_code', 'role_id', 'password', 'is_active',
        ]);
    }

    private function clearSessions(User $user): array
    {
        $counts = [
            'api_sessions' => $user->tokens()->delete(),
            'database_sessions' => DB::table('sessions')->where('user_id', $user->id)->delete(),
        ];
        $user->forceFill(['is_logged_in' => false, 'remember_token' => null])->save();

        return $counts;
    }

    private function expirePendingOtps(User $user): void
    {
        PhoneVerificationOtp::where('user_id', $user->id)->whereNull('verified_at')
            ->update(['expires_at' => now()]);
    }

    private function requireReason(string $reason): void
    {
        if (blank(trim($reason))) {
            throw ValidationException::withMessages(['reason' => 'Record the reason for this account change.']);
        }
    }

    private function record(User $user, string $action, ?string $reason = null, array $metadata = []): void
    {
        UserAccountEvent::create([
            'user_id' => $user->id, 'performed_by' => auth()->id(), 'action' => $action,
            'reason' => $reason, 'metadata' => $metadata,
        ]);
    }
}
