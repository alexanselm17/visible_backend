<?php

namespace App\Filament\Resources\AdvertImagesResource\Pages;

use App\Filament\Resources\AdvertImagesResource;
use App\Http\Controllers\ProductController;
use App\Http\Requests\ProductAdvertRequest;
use App\Repositories\Products\ProductRepositoryInterface;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateAdvertImages extends CreateRecord
{
    protected static string $resource = AdvertImagesResource::class;

    /**
     * Create a new advert record
     */
    public function create(bool $another = false): void
    {
        try {
            $this->authorizeAccess();

            $data = $this->form->getState();

            // ✅ Ensure valid_until is always set
            if (! empty($data['valid_until_date']) && ! empty($data['valid_until_time'])) {
                $time = strlen($data['valid_until_time']) === 5
                    ? $data['valid_until_time'].':00'
                    : $data['valid_until_time'];

                $data['valid_until'] = now()
                    ->setDateFrom(Carbon::parse($data['valid_until_date']))
                    ->setTimeFrom(Carbon::parse($time))
                    ->format('Y-m-d H:i:s');
            } else {
                // Default: tomorrow, same time as now
                $data['valid_until'] = now()
                    ->addDay()
                    ->format('Y-m-d H:i:s');
            }

            $this->validateRequiredFields($data);

            $request = $this->buildProductAdvertRequest($data);
            $response = $this->processAdvertUpload($request, $data['campaign_id']);

            $this->handleResponse($response);
        } catch (ValidationException $e) {
            $this->handleValidationError($e);
        } catch (\Throwable $e) {
            $this->handleUnexpectedError($e);
        }
    }

    /**
     * Build ProductAdvertRequest from form data
     */
    private function buildProductAdvertRequest(array $data): ProductAdvertRequest
    {
        $request = new ProductAdvertRequest;

        // Merge non-file fields
        $request->merge([
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'category' => $data['category'] ?? '',
            'badge' => $data['badge'] ?? [],
            'capacity' => $data['capacity'] ?? '',
            'valid_until' => $data['valid_until'] ?? '',
            'selling_price' => '0',
            'capital_invested' => $data['capital_invested'] ?? '',
            'target_audience' => $this->buildTargetAudience($data),
        ]);

        // Handle file uploads
        $this->attachMediaFiles($request, $data);

        return $request;
    }

    /**
     * Attach media files to the request
     */
    private function attachMediaFiles(ProductAdvertRequest $request, array $data): void
    {
        // Handle video and thumbnail
        if (! empty($data['video_path'])) {
            $this->attachFile($request, 'video', $data['video_path']);

            if (! empty($data['thumbnail'])) {
                $this->attachFile($request, 'thumbnail', $data['thumbnail']);
            }
        }
        // Handle image if no video
        elseif (! empty($data['image_path'])) {
            $this->attachFile($request, 'image', $data['image_path']);
        }
    }

    /**
     * Attach a single file to the request
     */
    private function attachFile(ProductAdvertRequest $request, string $key, $file): void
    {
        if ($file instanceof UploadedFile) {
            $request->files->set($key, $file);
        } elseif (is_string($file) && Storage::disk('public')->exists($file)) {
            $filePath = Storage::disk('public')->path($file);
            $fileName = basename($file);

            if (file_exists($filePath)) {
                $uploadedFile = new UploadedFile($filePath, $fileName, null, null, true);
                $request->files->set($key, $uploadedFile);
            } else {
                Log::warning("File not found: {$filePath}");
            }
        }
    }

    /**
     * Process the advert upload through ProductController
     */
    private function processAdvertUpload(ProductAdvertRequest $request, $campaignId)
    {
        if (empty($campaignId)) {
            throw new \InvalidArgumentException('Campaign ID is required');
        }

        $productRepository = app(ProductRepositoryInterface::class);
        $controller = new ProductController($productRepository);

        return $controller->uploadAdvertProducts($request, $campaignId);
    }

    /**
     * Handle the response from the upload process
     */
    private function handleResponse($response): void
    {
        $responseData = $response->getData();

        if (isset($responseData->ok) && $responseData->ok === true) {
            $this->showSuccessNotification($responseData);
            $this->redirectToIndex();
        } else {
            $this->showErrorNotification($responseData);
        }
    }

    /**
     * Build target audience array from form data
     */
    private function buildTargetAudience(array $data): ?string
    {
        $targetAudience = [
            'gender' => $data['gender'] ?? '',
            'county_id' => $data['county_id'] ?? '',
            'sub_county_id' => $data['sub_county_id'] ?? '',
        ];

        // Return null if all values are empty
        $hasValues = collect($targetAudience)
            ->filter(fn ($value) => ! empty($value))
            ->isNotEmpty();

        return $hasValues ? json_encode($targetAudience) : null;
    }

    /**
     * Validate required fields
     */
    private function validateRequiredFields(array $data): void
    {
        $requiredFields = ['name', 'campaign_id'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages([
                    $field => "The {$field} field is required.",
                ]);
            }
        }

        // Note: image_path validation is handled by the form's required() rule
        // video_path is optional as per form definition
    }

    /**
     * Show success notification
     */
    private function showSuccessNotification($responseData): void
    {
        Notification::make()
            ->title($responseData->message ?? 'Product advert uploaded successfully!')
            ->success()
            ->send();
    }

    /**
     * Show error notification
     */
    private function showErrorNotification($responseData): void
    {
        $errorMessage = $responseData->error ?? $responseData->message ?? 'Failed to upload product advert';

        Notification::make()
            ->title($errorMessage)
            ->danger()
            ->send();
    }

    /**
     * Handle validation errors
     */
    private function handleValidationError(ValidationException $e): void
    {
        Notification::make()
            ->title('Validation Error')
            ->body($e->getMessage())
            ->danger()
            ->send();
    }

    /**
     * Handle unexpected errors
     */
    private function handleUnexpectedError(\Throwable $e): void
    {
        Log::error('Unexpected error in CreateAdvertImages::create', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        Notification::make()
            ->title('An unexpected error occurred')
            ->body('Please try again or contact support if the problem persists.')
            ->danger()
            ->send();
    }

    /**
     * Redirect to index page
     */
    private function redirectToIndex(): void
    {
        $this->redirect($this->getResource()::getUrl('index'));
    }

    /**
     * Get redirect URL after creation
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
