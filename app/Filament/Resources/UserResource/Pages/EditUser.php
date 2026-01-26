<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Http\Controllers\AuthController;
use App\Http\Requests\UpdateProfileRequest;
use App\Repositories\Auth\AuthRepositoryInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            // Get the form data
            $data = $this->form->getState();

            // Create an update request and populate its data
            $updateRequest = new UpdateProfileRequest;

            // Only include fields that are present in the form data
            $updateFields = array_filter([
                'fullname' => $data['fullname'] ?? null,
                'username' => $data['username'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => isset($data['phone']) ? Str::start($data['phone'], '+254') : null,
                'national_id' => $data['national_id'] ?? null,
                'card_number' => $data['card_number'] ?? null,
                'petrol_id' => $data['petrol_id'] ?? null,
                'company_id' => FacadesAuth::user()->company_id,
            ], function ($value) {
                return ! is_null($value);
            });

            $updateRequest->merge($updateFields);

            // If password is being updated, include it
            if (isset($data['password']) && ! empty($data['password'])) {
                $updateRequest->merge([
                    'password' => $data['password'],
                    'password_confirmation' => $data['password_confirmation'] ?? $data['password'],
                ]);
            }

            // Manually set the route parameters for proper validation
            $updateRequest->setRouteResolver(function () {
                return tap(new \Illuminate\Routing\Route(['PUT'], '', []), function ($route) {
                    $route->setParameter('user', $this->record);
                });
            });
            $updateRequest->query->set('user_id', $this->record->id);
            $userRepository = app(AuthRepositoryInterface::class);
            $controller = new AuthController($userRepository);
            $response = $controller->updateProfile($updateRequest);
            $responseData = $response->getData();

            if ($responseData->ok === true) {
                if ($shouldSendSavedNotification) {
                    Notification::make()
                        ->title($responseData->message)
                        ->success()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title($responseData->message)
                    ->danger()
                    ->send();
            }

            if ($shouldRedirect) {
                $this->redirect($this->getRedirectUrl());
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error occurred while updating user.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            if ($shouldRedirect) {
                $this->redirect($this->getRedirectUrl());
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('station_report')
                ->label('Generate Employee Report')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->form([
                    DatePicker::make('from')
                        ->label('From Date')
                        ->required(),

                    DatePicker::make('to')
                        ->label('To Date')
                        ->after('from')
                        ->beforeOrEqual(now())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $petrolStationId = $this->record->petrol_id;

                    $from = $data['from'];
                    $to = $data['to'];

                    try {
                        return redirect()->route('reports.timely_personal_report', [
                            'petrol_id' => $petrolStationId,
                            'salesman' => $this->record->id,
                            'type' => 'station',
                            'from' => $from,
                            'to' => $to,
                        ]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body('Failed to generate station report: '.$e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
