<?php

namespace App\Http\Controllers\Api\Image;

use App\Http\Controllers\Controller;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Zxing\QrReader;
use Illuminate\Http\Request;

class ImageManipulationController extends Controller
{
    /**
     * Stamp the image with a dynamically scaled, numeric-only QR code.
     */
    public function encodeImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'identifier' => 'required|numeric|digits:10'
        ]);

        $identifier = $request->input('identifier');
        $uploadedFile = $request->file('image');

        $manager = new ImageManager(new Driver());
        $image = $manager->read($uploadedFile->getPathname());

        // Bumped to 15% and a minimum of 120px to survive high-res phone screenshots
        $qrSize = (int) max(120, $image->width() * 0.15);

        // The margin(2) remains to create the white box
        $qrCodeImage = (string) QrCode::format('png')
            ->size($qrSize)
            ->margin(2)
            ->errorCorrection('H')
            ->generate((string) $identifier);

        $qr = $manager->read($qrCodeImage);

        // The Critical Fix: 15 pixels of padding so the white margin 
        // doesn't touch the black WhatsApp background.
        $xOffset = 15;
        $yPosition = 15;

        $image->place($qr, 'top-left', $xOffset, $yPosition);

        $filename = 'stamped_' . time() . '.png';
        $path = storage_path('app/public/' . $filename);
        $image->toPng()->save($path);

        return response()->json([
            'message' => 'Image ready for WhatsApp Status',
            'filename' => $filename,
            'download_url' => url('/api/whatsapp/download/' . $filename),
            'identifier' => $identifier
        ]);
    }
    /**
     * Decode the 10-digit ID from a WhatsApp screenshot.
     */
    public function decodeScreenshot(Request $request)
    {
        $request->validate([
            'screenshot' => 'required|image|max:10240',
        ]);

        $uploadedFile = $request->file('screenshot');

        // Initialize the reader
        $qrcode = new QrReader($uploadedFile->getPathname());
        $text = $qrcode->text();

        // Verify we got a text result and that it matches our expected 10-digit format
        if ($text && preg_match('/^\d{10}$/', $text)) {
            return response()->json([
                'message' => 'Screenshot verified successfully!',
                'identifier' => $text
            ]);
        }

        return response()->json([
            'message' => 'Verification failed. The identifier was either destroyed by compression or cropped out.'
        ], 404);
    }

    /**
     * Force download the stamped image.
     */
    public function downloadImage($filename)
    {
        // Prevent directory traversal attacks
        $filename = basename($filename);
        $path = storage_path('app/public/' . $filename);

        if (!file_exists($path)) {
            return response()->json([
                'message' => 'Image not found.'
            ], 404);
        }

        // Return the file as a downloadable attachment
        return response()->download($path);
    }
}
