<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Card;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use App\Http\Controllers\SalesController;
use App\Http\Requests\RepaymentRequest;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use App\Models\Shift;
use App\Models\SysMeta;

class RepaymentCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'Customer Repayment';

    public function getBreadcrumb(): string
    {
        return 'Customer Repayment';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make()->schema([
                    Select::make('shift_id')
                        ->label('Shift')
                        ->options(function() {
                            return Shift::where('ended_at', null)
                                ->where('petrol_id', auth()->user()->petrol_id)
                                ->pluck('description', 'id');
                        })
                        ->required()
                        ->searchable()
                        ->placeholder('Select Shift'),
                    Select::make('payment.method')
                        ->label('Payment Method')
                        ->options(function() {
                            return SysMeta::where('meta_key', 'payment_method')
                                ->pluck('meta_value', 'meta_value');
                        })
                        ->required()
                        ->searchable()
                        ->placeholder('Select Payment Method'),
                    TextInput::make('payment.amount')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->label('Amount'),
                    
                ])
            ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            // Create the RepaymentRequest instance
            $request = new RepaymentRequest();
            
            // Format the data for the API request
            $requestData = [
                'payment' => [
                    'method' => $data['payment']['method'],
                    'amount' => $data['payment']['amount']
                ],
                'posted_by' => auth()->id()
            ];

            // Merge the data into the request
            $request->merge($requestData);

            // Call the controller method with the proper request object
            $response = app(SalesController::class)->customerRepayment(
                $request,
                $data['shift_id'],
                $record->id
            );
            
            $responseData = json_decode($response->getContent(), true);

            if (isset($responseData['status']) && $responseData['status'] === 'ok') {
                Notification::make()
                    ->title('Success')
                    ->body($responseData['message'] ?? 'Repayment successful')
                    ->success()
                    ->send();

                return $record;
            }

            Notification::make()
                ->title('Error')
                ->body($responseData['message'] ?? 'Failed to process repayment')
                ->danger()
                ->send();

            // throw new \Exception($responseData['message'] ?? 'Failed to process repayment');

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}