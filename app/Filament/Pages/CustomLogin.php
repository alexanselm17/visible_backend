<?php

// app/Filament/Pages/Auth/CustomLogin.php

namespace App\Filament\Pages\Auth;

use App\Http\Controllers\AuthController;
use App\Http\Requests\SignInRequest;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;

class CustomLogin extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $data = $this->form->getState();

            $request = new SignInRequest;
            $request->merge([
                'username' => $data['username'],
                'password' => $data['password'],
                'app_version' => '1.0.0',
            ]);

            $response = app(AuthController::class)->signin(
                request: $request
            );

            $responseData = $response->getData();

            if ($responseData->ok === true && $responseData->status === 'success') {
                // Create or update user in local database
                $userData = $responseData->data;

                $user = User::updateOrCreate(
                    ['username' => $userData->username],
                    [
                        'id' => $userData->id,
                        'name' => $userData->fullname,
                        'phone' => $userData->phone,
                        'role_id' => $userData->role->id ?? null,
                        'remember_token' => $responseData->token,
                    ]
                );

                // Set the authenticated user
                auth()->login($user);

                // Set success notification
                Notification::make()
                    ->title($responseData->message)
                    ->success()
                    ->send();

                // Return login response which will handle the redirect
                return app(LoginResponse::class);
            }

            Notification::make()
                ->title($responseData->message ?? 'Authentication failed')
                ->danger()
                ->send();

            return null;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error occurred')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    protected function getRedirectUrl(): string
    {
        return '/admin';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('username')
                    ->label('Username')
                    ->required()
                    ->placeholder('Enter your username')
                    ->extraInputAttributes(['class' => 'bg-gray-800 border-gray-700 text-white placeholder-gray-400']),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required()
                    ->placeholder('Enter your password')
                    ->extraInputAttributes(['class' => 'bg-gray-800 border-gray-700 text-white placeholder-gray-400']),
            ]);
    }

    public function getHeading(): string
    {
        return 'Welcome Back!';
    }

    public function getSubheading(): string
    {
        return 'Please sign in to access your dashboard';
    }

    // public function getView(): string
    // {
    //   return 'filament.pages.auth.custom-login';
    // }
}
