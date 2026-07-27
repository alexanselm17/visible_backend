<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Zxing\QrReader;
use Illuminate\Support\Str;

class ImageDecoderService
{
    /**
     * Extracts and decodes the QR code using a Multi-Pass Isolation & Zoom Strategy
     */
    public function decode($uploadedFile)
    {
        $manager = new ImageManager(new Driver());

        // Define our cropping strategies [Width, Height, X, Y, Contrast, Zoom]
        $strategies = [
            // Pass 1: Standard broad crop (Top 800px)
            ['w' => 1080, 'h' => 800, 'x' => 0, 'y' => 0, 'contrast' => 0, 'zoom' => false],
            
            // Pass 2: "The Sniper". Isolates the Top-Left where the banner sits.
            ['w' => 450, 'h' => 400, 'x' => 50, 'y' => 100, 'contrast' => 0, 'zoom' => false],
            
            // Pass 3: Sniper + Contrast (Helps separate gray mush into black/white)
            ['w' => 450, 'h' => 400, 'x' => 50, 'y' => 100, 'contrast' => 20, 'zoom' => false],
            
            // Pass 4: "The Magnifying Glass". Crops tightly around the QR and blows it up 300%
            // This is the ultimate fix for heavy WhatsApp compression.
            ['w' => 300, 'h' => 300, 'x' => 60, 'y' => 120, 'contrast' => 15, 'zoom' => true],
        ];

        foreach ($strategies as $strat) {
            $image = $manager->read($uploadedFile->getPathname());
            
            // Normalize width first so coordinates map correctly
            $image->scaleDown(width: 1080);

            // Apply the specific crop for this pass
            $image->crop($strat['w'], $strat['h'], $strat['x'], $strat['y']);
            
            $image->greyscale();

            if ($strat['contrast'] > 0) {
                $image->contrast($strat['contrast']);
            }

            // If this is the zoom pass, scale the tiny blurry box up massively
            if ($strat['zoom']) {
                $image->scale(width: $strat['w'] * 3);
            }

            $tempPath = public_path('storage/image_ads/decode/temp_qr_' . time() . '_' . Str::random(5) . '.png');
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            $image->toPng()->save($tempPath);

            $qrcode = new QrReader($tempPath);
            $text = trim((string) $qrcode->text());

            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            // If we found the 10-digit code, return it instantly
            if ($text && preg_match('/^\d{10}$/', $text)) {
                return $text;
            }
        }

        return null;
    }
}