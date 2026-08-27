<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Http\Controllers\ProductController;
use App\Repositories\Products\ProductRepositoryInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function handleRecordUpdate($record, array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $validUntil = isset($data['valid_until_date'], $data['valid_until_time'])
                ? Carbon::parse("{$data['valid_until_date']} {$data['valid_until_time']}")->toDateTimeString()
                : null;

            $request = new Request;
            $request->merge([
                'name' => $data['name'],
                'capital_invested' => $data['capital_invested'],
                'capacity' => $data['capacity'],
                'valid_until' => $validUntil,
            ]);

            $productRepository = app(ProductRepositoryInterface::class);
            $controller = new ProductController($productRepository);
            $response = $controller->updateCampaign($request, $record->id);
            $responseData = $response->getData();

            if ($responseData->ok === true) {
                Notification::make()
                    ->title($responseData->message ?? 'Campaign updated successfully')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title($responseData->error ?? 'Failed to update campaign')
                    ->danger()
                    ->send();
            }

            $record->refresh();

            return $record;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('An unexpected error occurred')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return $record;
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['valid_until'])) {
            $validUntil = Carbon::parse($data['valid_until']);
            $data['valid_until_date'] = $validUntil->toDateString();  // e.g. 2025-07-16
            $data['valid_until_time'] = $validUntil->format('H:i');   // e.g. 14:30
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }
}
