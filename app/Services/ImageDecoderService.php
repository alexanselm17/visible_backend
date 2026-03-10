<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Zxing\QrReader;
use Illuminate\Support\Str;

class ImageDecoderService
{
    /**
     * Extracts and decodes the QR code from an uploaded screenshot file.
     * Returns the decoded text, or null if it fails.
     */
    public function decode($uploadedFile)
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($uploadedFile->getPathname());

        // Normalize width and crop the top 800px
        $image->scaleDown(width: 1080);
        $image->crop(1080, 800, 0, 0);

        // Grayscale for contrast
        $image->greyscale();

        // Save temporary file (Using public_path to ensure Hostinger compatibility)
        $tempPath = public_path('storage/image_ads/decode/temp_qr_' . time() . '_' . Str::random(5) . '.png');

        // Ensure directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $image->toPng()->save($tempPath);

        // Run scanner
        $qrcode = new QrReader($tempPath);
        $text = $qrcode->text();

        // Cleanup
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        return $text;
    }
}
