<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use App\Http\Controllers\SalesController;
use App\Http\Requests\ReconcileCustomerRequest;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ReconcileCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
    
    protected static ?string $title = 'Reconcile Customer';

    public function getBreadcrumb(): string
    {
        return 'Reconcile Customer';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make()->schema([
                    TextInput::make('balance')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->label('Balance'),
                    Hidden::make('type')
                        ->default('Reconciliation'),
                    Hidden::make('posted_by')
                        ->default(Auth::id()),
                ])
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Reconcile'),
            $this->getCancelFormAction(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            // Create and populate the request
            $request = new ReconcileCustomerRequest();
            $request->merge([
                'type' => 'Reconciliation',
                'balance' => $data['balance'],
                'posted_by' => Auth::id(),
            ]);

            // Pass the request first, then the customer ID
            $response = app(SalesController::class)->reconcileCustomer(
                $request, 
                $record->id
            );
            
            $responseData = json_decode($response->getContent(), true);

            if (isset($responseData['status']) && $responseData['status'] === 'success') {
                Notification::make()
                    ->title('Success')
                    ->body($responseData['message'] ?? 'Reconciliation successful')
                    ->success()
                    ->send();

                return $record;
            }

            Notification::make()
                ->title('Error')
                ->body($responseData['message'] ?? 'Failed to reconcile')
                ->danger()
                ->send();

            throw new \Exception($responseData['message'] ?? 'Failed to reconcile');

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
        return 'Customer reconciled successfully';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['type'] = 'Reconciliation';
        $data['posted_by'] = Auth::id();
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'Reconciliation';
        $data['posted_by'] = Auth::id();
        return $data;
    }
}