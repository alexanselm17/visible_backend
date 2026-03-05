<?php

namespace App\Http\Controllers\Api\Image;

use App\Http\Controllers\Controller;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Http\Request;
use Zxing\QrReader;


class ImageManipulationController extends Controller
{
    /**
     * Stamp the image with a dynamically scaled, numeric-only QR code.
     */

    public function encodeImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'identifier' => 'required|numeric|digits:10',
            'ad_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        $identifier = $request->input('identifier');
        $uploadedFile = $request->file('image');

        $manager = new ImageManager(new Driver());

        // ==========================================
        // 1. THE CANVAS (1080x1920)
        // ==========================================
        $canvasWidth = 1080;
        $canvasHeight = 1920;
        $canvas = $manager->create($canvasWidth, $canvasHeight)->fill('000000');


        // ==========================================
        // 2. THE HEADER (Fixing the tiny QR code)
        // ==========================================
        $templatePath = public_path('images/base_template.png');
        if (!file_exists($templatePath)) {
            return response()->json(['message' => 'Template image missing.'], 500);
        }
        $header = $manager->read($templatePath);

        // "White Sticker" QR Math
        $circleDiameter = $header->height();
        $qrSize = (int) ($circleDiameter * 0.65);
        $qrCodeImage = (string) QrCode::format('png')
            ->size($qrSize)->margin(2)->errorCorrection('H')->generate((string) $identifier);
        $qr = $manager->read($qrCodeImage);

        $qrOffset = (int) (($circleDiameter / 2) - ($qrSize / 2));
        $header->place($qr, 'top-left', $qrOffset, $qrOffset);

        // --- THE FIX IS HERE ---
        // Instead of forcing a tiny height, we scale by width. 
        // 950px width guarantees it stretches nicely across the screen 
        // while keeping the QR code large enough for scanners to easily read.
        $header->scaleDown(width: 950);

        // Place it 120px from the top (Clears the WhatsApp Profile UI)
        $headerTopY = 120;
        $canvas->place($header, 'top', 0, $headerTopY);
        $headerBottomY = $headerTopY + $header->height();


        // ==========================================
        // 3. THE ADVERT (Full width, No Cropping)
        // ==========================================
        // Default bottom safe zone if no ad is uploaded
        $adTopY = $canvasHeight - 120;

        if ($request->hasFile('ad_image')) {
            $adFile = $request->file('ad_image');
            $ad = $manager->read($adFile->getPathname());

            // Step A: Proportionally scale the ad to strictly fit the 1080px width.
            // This does NOT crop the image; it keeps the entire thing visible.
            $ad->scale(width: 1080);

            // Step B: Safety Net
            // If the scaled ad is excessively tall (taller than 400px), it will ruin the layout.
            // We scale it down to a safe height box. It will no longer touch the side edges,
            // but the entire image will still be 100% visible and uncropped.
            if ($ad->height() > 400) {
                $ad->scaleDown(width: 1080, height: 400);
            }

            // Calculate exact placement to push it up 120px from the bottom
            $adTopY = $canvasHeight - 120 - $ad->height();

            // Place the ad. 'top' anchor keeps it horizontally centered if the safety net triggered.
            $canvas->place($ad, 'top', 0, $adTopY);
        }


        // ==========================================
        // 4. THE MAIN IMAGE (~3/4 of the Screen)
        // ==========================================
        $mainImage = $manager->read($uploadedFile->getPathname());

        // Calculate the massive empty space left in the middle
        // (From Y: 250 down to Y: 1550 = 1300 pixels of vertical space!)
        $padding = 30; // Small 30px gap so images don't touch
        $availableTop = $headerBottomY + $padding;
        $availableBottom = $adTopY - $padding;
        $availableHeight = $availableBottom - $availableTop;

        // Scale the main image into this massive space
        $mainImage->scaleDown(width: 1080, height: $availableHeight);

        // Center it vertically inside the available area
        $mainImageY = $availableTop + (int) (($availableHeight - $mainImage->height()) / 2);

        $canvas->place($mainImage, 'top', 0, $mainImageY);


        // ==========================================
        // 5. SAVE & RESPOND
        // ==========================================
        $filename = 'stamped_' . time() . '.png';
        $path = storage_path('app/public/image_ads/encoded/' . $filename);
        $canvas->toPng()->save($path);

        return response()->json([
            'message' => 'Fractional layout generated perfectly',
            'filename' => $filename,
            'download_url' => url('/api/whatsapp/download/' . $filename),
            'identifier' => $identifier
        ]);
    }
    // public function encodeImage(Request $request)
    // {
    //     $request->validate([
    //         'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
    //         'identifier' => 'required|numeric|digits:10'
    //     ]);

    //     $identifier = $request->input('identifier');
    //     $uploadedFile = $request->file('image');

    //     $manager = new ImageManager(new Driver());
    //     $image = $manager->read($uploadedFile->getPathname());

    //     // Bumped to 15% and a minimum of 120px to survive high-res phone screenshots
    //     $qrSize = (int) max(120, $image->width() * 0.15);

    //     // The margin(2) remains to create the white box
    //     $qrCodeImage = (string) QrCode::format('png')
    //         ->size($qrSize)
    //         ->margin(2)
    //         ->errorCorrection('H')
    //         ->generate((string) $identifier);

    //     $qr = $manager->read($qrCodeImage);

    //     // The Critical Fix: 15 pixels of padding so the white margin 
    //     // doesn't touch the black WhatsApp background.
    //     $xOffset = 15;
    //     $yPosition = 15;

    //     $image->place($qr, 'top-left', $xOffset, $yPosition);

    //     $filename = 'stamped_' . time() . '.png';
    //     $path = storage_path('app/public/' . $filename);
    //     $image->toPng()->save($path);

    //     return response()->json([
    //         'message' => 'Image ready for WhatsApp Status',
    //         'filename' => $filename,
    //         'download_url' => url('/api/whatsapp/download/' . $filename),
    //         'identifier' => $identifier
    //     ]);
    // }
    // /**
    //  * Decode the 10-digit ID from a WhatsApp screenshot.
    //  */
    public function decodeScreenshot(Request $request)
    {
        $request->validate([
            'screenshot' => 'required|image|max:10240',
        ]);

        $uploadedFile = $request->file('screenshot');

        // 1. PRE-PROCESS THE SCREENSHOT
        // Initialize Intervention Image
        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        $image = $manager->read($uploadedFile->getPathname());

        // Normalize the width to 1080px (in case it's a massive 4K phone screenshot)
        $image->scaleDown(width: 1080);

        // Crop to just the top 800 pixels. 
        // We know the QR code is safely in this top section. 
        // This throws away the meme and the ad so they don't confuse the scanner.
        $image->crop(1080, 800, 0, 0);

        // Convert to grayscale. Removing color drastically improves the PHP scanner's accuracy.
        $image->greyscale();

        // Save this cleaned, cropped sliver to a temporary file
        $tempPath = storage_path('app/public/image_ads/decode/temp_qr_' . time() . '.png');
        $image->toPng()->save($tempPath);

        // 2. RUN THE SCANNER
        // Feed the clean, cropped temp file to the QrReader instead of the raw screenshot
        $qrcode = new \Zxing\QrReader($tempPath);
        $text = $qrcode->text();

        // 3. CLEAN UP
        // Delete the temporary image so we don't clog up your server storage
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        // 4. VERIFY
        // Check if we got a text result and that it matches our 10-digit format
        if ($text && preg_match('/^\d{10}$/', $text)) {
            return response()->json([
                'message' => 'Screenshot verified successfully!',
                'identifier' => $text
            ]);
        }

        return response()->json([
            'message' => 'Verification failed. The identifier was either destroyed by compression or cropped out.',
            'raw_output' => $text
        ], 404);
    }

    /**
     * Force download the stamped image.
     */
    public function downloadImage($filename)
    {
        // Prevent directory traversal attacks
        $filename = basename($filename);
        $path = storage_path('app/public/image_ads/encoded/' . $filename);

        if (!file_exists($path)) {
            return response()->json([
                'message' => 'Image not found.'
            ], 404);
        }

        // Return the file as a downloadable attachment
        return response()->download($path);
    }
}
