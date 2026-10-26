<?php

namespace App\Filament\Support;

use App\Models\User;
use App\Services\AdminUserService;
use App\Support\AdminAccess;
use Filament\Forms\Components as F;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class UserAccountActions
{
    /** Shared controls on the user list and the individual profile page. */
    public static function make(string $actionClass = Action::class, ?User $record = null): array
    {
        $manage = fn (User $record) => ! $record->trashed() && AdminUserService::canManage($record);
        $verify = fn (User $record) => ! $record->trashed() && AdminUserService::canManage($record, true);
        $reason = fn () => [F\Textarea::make('reason')->label('Reason / evidence checked')->required()->maxLength(1000)];
        $actions = [
            $actionClass::make('activate')->label('Activate account')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (User $record) => $manage($record) && ! $record->is_active)
                ->requiresConfirmation()->action(fn (User $record) => static::done(fn () => app(AdminUserService::class)->setActive($record, true), 'Account activated')),
            $actionClass::make('deactivate')->label('Deactivate account')->icon('heroicon-o-no-symbol')->color('danger')
                ->visible(fn (User $record) => $manage($record) && $record->is_active && $record->id !== auth()->id())
                ->form($reason())->requiresConfirmation()->modalDescription('Revokes API access, database sessions and remembered logins.')
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->setActive($record, false, $data['reason']), 'Account deactivated')),
            $actionClass::make('verify_account')->label('Verify account')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (User $record) => $verify($record) && ! $record->is_verified)->form($reason())->requiresConfirmation()
                ->modalDescription('Records account approval. Phone and email verification have their own controls.')
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->setVerification($record, 'account', true, $data['reason']), 'Account verified')),
            $actionClass::make('unverify_account')->label('Remove account verification')->icon('heroicon-o-shield-exclamation')
                ->visible(fn (User $record) => $verify($record) && $record->is_verified)->form($reason())->requiresConfirmation()
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->setVerification($record, 'account', false, $data['reason']), 'Account verification removed')),
            $actionClass::make('resend_otp')->label('Send / resend phone OTP')->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->visible(fn (User $record) => $manage($record) && ! $record->phone_verified_at)
                ->requiresConfirmation()->modalDescription('Sends an SMS to the saved phone number using TalkSasa. Normal resend limits apply.')
                ->action(fn (User $record) => static::done(fn () => app(AdminUserService::class)->sendOtp($record), 'Verification code sent')),
            $actionClass::make('verify_phone_otp')->label('Verify phone with OTP')->icon('heroicon-o-device-phone-mobile')
                ->visible(fn (User $record) => $verify($record) && ! $record->phone_verified_at)
                ->form([F\TextInput::make('otp')->label('6-digit code supplied by the user')->required()->inputMode('numeric')->regex('/^\d{6}$/')
                    ->helperText('Uses the signup verification flow, which also marks the account verified.')])
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->verifyPhoneOtp($record, $data['otp']), 'Phone verified using OTP')),
            $actionClass::make('mark_phone_verified')->label('Verify phone after staff review')->icon('heroicon-o-phone')
                ->visible(fn (User $record) => $verify($record) && ! $record->phone_verified_at)->form($reason())->requiresConfirmation()
                ->modalDescription('Use after checking phone ownership outside the OTP flow. The reason and staff member are recorded.')
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->setVerification($record, 'phone', true, $data['reason']), 'Phone marked verified')),
            $actionClass::make('reset_phone_verification')->label('Remove phone verification')->icon('heroicon-o-phone-x-mark')
                ->visible(fn (User $record) => $verify($record) && (bool) $record->phone_verified_at)->form($reason())->requiresConfirmation()
                ->modalDescription('Expires pending codes and revokes sessions. The user will need to verify the phone again.')
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->setVerification($record, 'phone', false, $data['reason']), 'Phone verification removed')),
            $actionClass::make('verify_email')->label('Verify email after staff review')->icon('heroicon-o-envelope')
                ->visible(fn (User $record) => $verify($record) && ! $record->email_verified_at)->form($reason())->requiresConfirmation()
                ->modalDescription('Records an email ownership check completed by staff. It does not send a verification email.')
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->setVerification($record, 'email', true, $data['reason']), 'Email marked verified')),
            $actionClass::make('reset_email_verification')->label('Remove email verification')->icon('heroicon-o-envelope-open')
                ->visible(fn (User $record) => $verify($record) && (bool) $record->email_verified_at)->form($reason())->requiresConfirmation()
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->setVerification($record, 'email', false, $data['reason']), 'Email verification removed')),
            $actionClass::make('reset_password')->label('Reset password')->icon('heroicon-o-key')
                ->visible($manage)->form([
                    F\TextInput::make('password')->label('New password')->password()->revealable()->required()->minLength(8)->confirmed(),
                    F\TextInput::make('password_confirmation')->label('Confirm password')->password()->required(),
                ])->modalDescription('Existing API and database sessions will be revoked. Passwords are never included in the audit history.')
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->update($record, ['password' => $data['password']]), 'Password updated; sessions revoked')),
            $actionClass::make('revoke_sessions')->label('Revoke login sessions')->icon('heroicon-o-arrow-right-on-rectangle')
                ->visible($manage)->requiresConfirmation()->modalDescription('Revokes API tokens, database sessions and remembered logins. The account stays active.')
                ->action(fn (User $record) => static::done(fn () => app(AdminUserService::class)->revokeSessions($record), 'Login sessions revoked')),
            $actionClass::make('delete')->label('Delete account')->icon('heroicon-o-trash')->color('danger')
                ->visible(fn (User $record) => $verify($record) && $record->id !== auth()->id())->form($reason())->requiresConfirmation()
                ->modalDescription('This is a recoverable deletion. The account is deactivated and sessions are revoked; financial and posting history are retained.')
                ->action(fn (User $record, array $data) => static::done(fn () => app(AdminUserService::class)->delete($record, $data['reason']), 'Account deleted; available in Deleted users')),
            $actionClass::make('restore')->label('Restore deleted account')->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (User $record) => $record->trashed() && AdminAccess::allows('settings'))->requiresConfirmation()
                ->modalDescription('Restores the account as inactive. Activate it separately when it is ready to use.')
                ->action(fn (User $record) => static::done(fn () => app(AdminUserService::class)->restore($record), 'Account restored as inactive')),
        ];
        if ($record) {
            foreach ($actions as $action) {
                $action->record($record)->after(function ($livewire) use ($record) {
                    $record->refresh();
                    if ($record->trashed() && $livewire instanceof \App\Filament\Resources\UserResource\Pages\EditUser) {
                        $livewire->redirect(\App\Filament\Resources\UserResource::getUrl('view', ['record' => $record]));
                    }
                });
            }
        }

        return $actions;
    }

    private static function done(callable $operation, string $message): void
    {
        $operation();
        Notification::make()->title($message)->success()->send();
    }
}
