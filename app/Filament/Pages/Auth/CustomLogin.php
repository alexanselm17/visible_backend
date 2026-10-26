<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login;
use Illuminate\Validation\ValidationException;

class CustomLogin extends Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')->label('Username')->required()
            ->autocomplete('username')->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return ['username' => $data['username'], 'password' => $data['password'], 'is_active' => true];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages(['data.username' => 'These credentials do not match an active staff account.']);
    }

    public function getHeading(): string
    {
        return 'Welcome to Visible';
    }

    public function getSubheading(): string
    {
        return 'Sign in to manage campaigns, tokens and performance earnings.';
    }
}
