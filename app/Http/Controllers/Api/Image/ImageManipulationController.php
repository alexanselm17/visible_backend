<?php

namespace App\Http\Controllers\Api\Image;

use App\Http\Controllers\Controller;
use App\Models\AdvertImages;
use App\Services\AdvertQrCodeService;
use App\Services\ImageDecoderService;
use App\Services\ImageEncoderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImageManipulationController extends Controller
{
    public function encodeImage(Request $request, ImageEncoderService $encoder)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'identifier' => 'required|numeric|digits:10',
            'advert_id' => 'required|string|exists:advert_images,id',
            'header_text' => 'nullable|string|max:1000',
            'caption' => 'nullable|string|max:120',
            'layout' => ['nullable', 'string', Rule::in(ImageEncoderService::supportedLayouts())],
        ]);

        $identifier = $request->input('identifier');
        $uploadedFile = $request->file('image');
        $advertId = $request->input('advert_id');
        $layout = $request->input('layout', 'story');

        $advertRecord = AdvertImages::findOrFail($advertId);
        $adImagePath = public_path('storage/' . ltrim($advertRecord->image_path, '/'));

        if (!file_exists($adImagePath)) {
            return response()->json([
                'message' => 'Advert image file not found.'
            ], 404);
        }

        $watermarkRef = $this->watermarkReference((string) $identifier, (string) $advertId);
        $encoded = $encoder->encode(
            $uploadedFile->getPathname(),
            $watermarkRef,
            $adImagePath,
            null,
            null,
            null,
            $layout
        );

        return response()->json([
            'message' => 'Layout generated successfully',
            'filename' => $encoded['filename'],
            'download_url' => url('/storage/image_ads/encoded/' . $encoded['filename']),
            'watermark_ref' => $watermarkRef,
            'advert_id' => $advertId,
            'layout' => $layout,
            'available_layouts' => ImageEncoderService::supportedLayouts(),
        ]);
    }

    private function watermarkReference(string $identifier, string $advertId): string
    {
        $key = (string) config('app.key');

        return 'IMG_REF_'.strtoupper(substr(hash_hmac('sha256', $identifier.'|'.$advertId, $key), 0, 16));
    }

    public function downloadPersonalizedAdvert(
        Request $request,
        string $advertId,
        ImageEncoderService $encoder,
        AdvertQrCodeService $qrCodes
    ) {
        $advert = AdvertImages::findOrFail($advertId);
        $identifier = trim((string) $request->user()?->my_code);

        if (! preg_match('/^\d{10}$/', $identifier)) {
            return response()->json([
                'message' => 'Your account does not have a valid 10-digit QR identifier.',
            ], 422);
        }

        if (! $advert->image_path) {
            return response()->json([
                'message' => 'This advert does not have an image that can be downloaded.',
            ], 422);
        }

        $sourcePath = public_path('storage/'.ltrim($advert->image_path, '/'));
        if (! is_file($sourcePath)) {
            return response()->json([
                'message' => 'Advert image file not found.',
            ], 404);
        }

        $qrUrl = $qrCodes->issue($request->user(), $advert);
        $visibleProofCode = $qrCodes->visibleCodeFor($request->user(), $advert);
        $encoded = $encoder->encode($sourcePath, $qrUrl, null, null, null, $visibleProofCode);
        $downloadName = Str::slug((string) $advert->name ?: 'advert').'-personalized.png';

        $response = response()->download($encoded['path'], $downloadName, [
            'Content-Type' => 'image/png',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('max-age', 0);

        return $response->deleteFileAfterSend(true);
    }

    public function decodeScreenshot(
        Request $request,
        ImageDecoderService $decoder,
        AdvertQrCodeService $qrCodes
    ) {
        $request->validate([
            'screenshot' => 'required|image|max:10240',
            'advert_id' => 'required|uuid|exists:advert_images,id',
        ]);

        $advert = AdvertImages::findOrFail($request->input('advert_id'));
        $text = $decoder->decode($request->file('screenshot'));
        try {
            $verified = $qrCodes->verifyOrFail(
                (string) $text,
                $request->user(),
                $advert
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            return response()->json([
                'message' => collect($errors)->flatten()->first() ?? 'QR code verification failed.',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'message' => 'Screenshot verified successfully!',
            'identifier' => $verified->identifier_snapshot,
            'advert_id' => $verified->advert_id,
        ]);
    }

    public function downloadImage($filename)
    {
        $filename = basename($filename);
        $path = storage_path('app/public/image_ads/encoded/' . $filename);

        if (!file_exists($path)) {
            return response()->json([
                'message' => 'Image not found.'
            ], 404);
        }

        return response()->download($path);
    }
}
