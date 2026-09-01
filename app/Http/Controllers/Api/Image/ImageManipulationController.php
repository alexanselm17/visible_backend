<?php

namespace App\Http\Controllers\Api\Image;

use App\Http\Controllers\Controller;
use App\Models\AdvertImages;
use App\Services\ImageEncoderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ImageManipulationController extends Controller
{
    public function encodeImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'identifier' => 'required|numeric|digits:10',
            'advert_id' => 'required|string|exists:advert_images,id',
            'header_text' => 'nullable|string|max:1000',
            'caption' => 'nullable|string|max:120',
        ]);

        $identifier = $request->input('identifier');
        $uploadedFile = $request->file('image');
        $advertId = $request->input('advert_id');
        $captionText = $request->input('caption');

        $headerText = $request->input(
            'header_text',
            'The content media attached to this advertisement campaign has no affiliation to VISIBLE DM or its partners but has been attached solely by the holder of this account in full knowledge and consent. For any queries, contact 0712345678.'
        );

        $advertRecord = AdvertImages::findOrFail($advertId);
        $adImagePath = public_path('storage/' . ltrim($advertRecord->image_path, '/'));

        if (!file_exists($adImagePath)) {
            return response()->json([
                'message' => 'Advert image file not found.'
            ], 404);
        }

        $templatePath = public_path('images/not_full_sample.jpeg');
        if (!file_exists($templatePath)) {
            return response()->json([
                'message' => 'Blank template image missing.'
            ], 500);
        }

        $titleFontPath = public_path('fonts/Roboto_SemiCondensed-SemiBold.ttf');
        $bodyFontPath = public_path('fonts/Roboto_SemiCondensed-Regular.ttf');

        if (!file_exists($titleFontPath)) {
            $titleFontPath = public_path('fonts/Roboto-Bold.ttf');
        }
        if (!file_exists($bodyFontPath)) {
            $bodyFontPath = public_path('fonts/Roboto-Regular.ttf');
        }

        $manager = new ImageManager(new Driver());

        // ======================================================
        // 1. FINAL CANVAS
        // ======================================================
        $canvasWidth = 1080;
        $canvasHeight = 1350;
        $canvas = $manager->create($canvasWidth, $canvasHeight)->fill('000000');

        // ======================================================
        // 2. TOP BANNER - CENTERED, FULL, SHARPER TEXT
        // ======================================================
        $topMargin = 18;
        $headerTargetWidth = 920;

        $header = $manager->read($templatePath);
        $header->scaleDown(width: $headerTargetWidth);

        $headerWidth = $header->width();
        $headerHeight = $header->height();

        // QR placement on final-size banner
        $sideCircleWidth = $headerHeight;
        $qrSize = (int) round($sideCircleWidth * 0.60);

        $qrCodeImage = (string) QrCode::format('png')
            ->size($qrSize)
            ->margin(1)
            ->errorCorrection('H')
            ->generate((string) $identifier);

        $qrOffsetX = (int) round(($sideCircleWidth - $qrSize) / 2);
        $qrOffsetY = (int) round(($headerHeight - $qrSize) / 2);

        $header->place(
            $manager->read($qrCodeImage),
            'top-left',
            $qrOffsetX,
            $qrOffsetY
        );

        // Text zone between QR and logo
        $textPadding = (int) round($headerHeight * 0.05);
        $textLeft = $sideCircleWidth + $textPadding;
        $textRight = $headerWidth - $sideCircleWidth - $textPadding;
        $textCenterX = (int) round(($textLeft + $textRight) / 2);

        $titleY = (int) round($headerHeight * 0.08);
        $underlineY = (int) round($headerHeight * 0.19);
        $bodyY = (int) round($headerHeight * 0.23);

        if (file_exists($titleFontPath)) {
            $header->text('DISCLAIMER', $textCenterX, $titleY, function ($font) use ($titleFontPath, $headerHeight) {
                $font->file($titleFontPath);
                $font->size((int) round($headerHeight * 0.11));
                $font->color('000000');
                $font->align('center');
                $font->valign('top');
            });
        }

        $underlineWidth = (int) round(($textRight - $textLeft) * 0.42);
        $header->drawLine(function ($line) use ($textCenterX, $underlineWidth, $underlineY) {
            $line->from((int) round($textCenterX - ($underlineWidth / 2)), $underlineY);
            $line->to((int) round($textCenterX + ($underlineWidth / 2)), $underlineY);
            $line->color('000000');
            $line->width(1);
        });

        $bodyText = preg_replace('/^DISCLAIMER\s*/i', '', trim($headerText));
        $bodyText = trim($bodyText);
        $wrappedBodyText = wordwrap($bodyText, 34, "\n");

        if (file_exists($bodyFontPath)) {
            $header->text($wrappedBodyText, $textCenterX, $bodyY, function ($font) use ($bodyFontPath, $headerHeight) {
                $font->file($bodyFontPath);
                $font->size((int) round($headerHeight * 0.09));
                $font->color('000000');
                $font->align('center');
                $font->valign('top');
                $font->lineHeight(1.30);
            });
        }

        $headerTopY = $topMargin;
        $headerX = (int) round(($canvasWidth - $header->width()) / 2);

        $canvas->place($header, 'top-left', $headerX, $headerTopY);
        $headerBottomY = $headerTopY + $header->height();
        // ======================================================
        // 3. BOTTOM AD - FULL WIDTH COVER
        // ======================================================
        $ad = $manager->read($adImagePath);

        $bottomMargin = 18;
        $adTargetWidth = 1080;
        $adTargetHeight = 200;
        $adTopY = $canvasHeight - $bottomMargin - $adTargetHeight;

        $adSrcW = $ad->width();
        $adSrcH = $ad->height();

        $adScale = max($adTargetWidth / $adSrcW, $adTargetHeight / $adSrcH);
        $adResizedW = (int) ceil($adSrcW * $adScale);
        $adResizedH = (int) ceil($adSrcH * $adScale);

        $ad->resize($adResizedW, $adResizedH);

        $adCropX = (int) max(0, floor(($adResizedW - $adTargetWidth) / 2));
        $adCropY = (int) max(0, floor(($adResizedH - $adTargetHeight) / 2));

        $ad->crop($adTargetWidth, $adTargetHeight, $adCropX, $adCropY);
        $canvas->place($ad, 'top-left', 0, $adTopY);

        // ======================================================
        // 4. MAIN IMAGE
        // ======================================================
        $mainImage = $manager->read($uploadedFile->getPathname());

        $sidePadding = 24;
        $sectionGap = 18;
        $captionSpace = ($captionText && file_exists($bodyFontPath)) ? 70 : 0;

        $availableTop = $headerBottomY + $sectionGap;
        $availableBottom = $adTopY - $sectionGap;

        $targetX = $sidePadding;
        $targetY = $availableTop + $captionSpace;
        $targetWidth = $canvasWidth - ($sidePadding * 2);
        $targetHeight = $availableBottom - $availableTop - $captionSpace;

        if ($captionText && file_exists($bodyFontPath)) {
            $lineY = $availableTop + 14;

            $canvas->drawLine(function ($line) use ($sidePadding, $canvasWidth, $lineY) {
                $line->from($sidePadding, $lineY);
                $line->to($canvasWidth - $sidePadding, $lineY);
                $line->color('000000');
                $line->width(2);
            });

            $canvas->text($captionText, $sidePadding, $lineY + 12, function ($font) use ($bodyFontPath) {
                $font->file($bodyFontPath);
                $font->size(24);
                $font->color('000000');
                $font->align('left');
                $font->valign('top');
                $font->lineHeight(1.18);
            });
        }

        $srcW = $mainImage->width();
        $srcH = $mainImage->height();

        $scale = max($targetWidth / $srcW, $targetHeight / $srcH);
        $resizedW = (int) ceil($srcW * $scale);
        $resizedH = (int) ceil($srcH * $scale);

        $mainImage->resize($resizedW, $resizedH);

        $cropX = (int) max(0, floor(($resizedW - $targetWidth) / 2));
        $cropY = (int) max(0, floor(($resizedH - $targetHeight) / 2));

        $mainImage->crop($targetWidth, $targetHeight, $cropX, $cropY);
        $canvas->place($mainImage, 'top-left', $targetX, $targetY);

        // ======================================================
        // 5. SAVE
        // ======================================================
        $filename = 'stamped_' . time() . '.png';
        $saveDirectory = public_path('storage/image_ads/encoded');
        $savePath = $saveDirectory . '/' . $filename;

        if (!file_exists($saveDirectory)) {
            mkdir($saveDirectory, 0755, true);
        }

        $canvas->toPng()->save($savePath);

        return response()->json([
            'message' => 'Layout generated successfully',
            'filename' => $filename,
            'download_url' => url('/storage/image_ads/encoded/' . $filename),
            'identifier' => $identifier,
            'advert_id' => $advertId,
        ]);
    }

    public function downloadPersonalizedAdvert(
        Request $request,
        string $advertId,
        ImageEncoderService $encoder
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

        $encoded = $encoder->encode(
            $sourcePath,
            $identifier,
            captionText: Str::limit((string) $advert->name, 120)
        );
        $downloadName = Str::slug((string) $advert->name ?: 'advert').'-personalized.png';

        $response = response()->download($encoded['path'], $downloadName, [
            'Content-Type' => 'image/png',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('max-age', 0);

        return $response->deleteFileAfterSend(true);
    }

    public function decodeScreenshot(Request $request, \App\Services\ImageDecoderService $decoder)
    {
        $request->validate([
            'screenshot' => 'required|image|max:10240',
        ]);

        $uploadedFile = $request->file('screenshot');
        $text = $decoder->decode($uploadedFile);

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
