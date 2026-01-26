<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Http\Controllers\AuthController;
use App\Http\Requests\SignUp;
use App\Repositories\Auth\AuthRepositoryInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function create(bool $another = false): void
    {
        try {
            // Retrieve form data
            $data = $this->form->getState();

            // Create a new user request and populate its data
            $userRequest = new SignUp;
            $userRequest->merge([
                'fullname' => $data['fullname'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => Str::start($data['phone'], '+254'),
                'national_id' => $data['national_id'],
                'card_number' => $data['card_number'],
                'petrol_id' => $data['petrol_id'],
                'password' => $data['password'],
                'company_id' => FacadesAuth::user()->company_id,

            ]);

            $userRepository = app(AuthRepositoryInterface::class);
            $controller = new AuthController($userRepository);
            $response = $controller->signup($userRequest);
            $responseData = $response->getData();

            if ($responseData->ok === true) {
                Notification::make()
                    ->title($responseData->message)
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title($responseData->message)
                    ->danger()
                    ->send();
            }

            // Redirect to the index page
            $this->redirect($this->getRedirectUrl());
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error occurred during user creation.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            // Redirect to prevent breaking the flow
            $this->redirect($this->getRedirectUrl());
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
