<?php

namespace App\Filament\Resources\AdvertImagesResource\Pages;

use App\Filament\Resources\AdvertImagesResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use App\Http\Requests\ProductAdvertRequest;
use App\Http\Controllers\ProductController;
use App\Repositories\Products\ProductRepositoryInterface;

class CreateAdvertImages extends CreateRecord
{
    protected static string $resource = AdvertImagesResource::class;

    public function create(bool $another = false): void
    {
        try {
            $data = $this->form->getState();
            $request = new ProductAdvertRequest();

            // Merge non-file fields
            $request->merge([
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'category' => $data['category'] ?? '',
                'badge' => $data['badge'] ?? '',
            ]);

            // Attach the file: either image or video (as fallback)
            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $request->files->set('image', $data['image']);
            } elseif (isset($data['video']) && $data['video'] instanceof UploadedFile) {
                $request->files->set('image', $data['video']); // fallback for image validation
                $request->files->set('video', $data['video']); // still attach video separately
            }

            // Also attach the video if both are set
            if (isset($data['video']) && $data['video'] instanceof UploadedFile) {
                $request->files->set('video', $data['video']);
            }

            // Get campaign ID from hidden input
            $campaignId = $data['campaign_id'];

            // Use controller to handle logic
            $productRepository = app(ProductRepositoryInterface::class);
            $controller = new ProductController($productRepository);
            $response = $controller->uploadAdvertProducts($request, $campaignId);

            $responseData = $response->getData();

            if (isset($responseData->ok) && $responseData->ok === true) {
                Notification::make()
                    ->title($responseData->message ?? 'Advert uploaded successfully!')
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            } else {
                Notification::make()
                    ->title($responseData->error ?? $responseData->message ?? 'Failed to upload advert')
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('An unexpected error occurred')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
