<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ImageEncoderService
{
    public const DEFAULT_HEADER = 'The content media attached to this advertisement campaign has no affiliation to VISIBLE DM or its partners but has been attached solely by the holder of this account in full knowledge and consent. For any queries, contact 0712345678.';

    /**
     * Create a post-ready image containing a QR code tied to one user.
     *
     * @return array{path: string, filename: string}
     */
    public function encode(
        string $mainImagePath,
        string $identifier,
        ?string $footerImagePath = null,
        ?string $headerText = null,
        ?string $captionText = null
    ): array {
        if (! preg_match('/^\d{10}$/', $identifier)) {
            throw ValidationException::withMessages([
                'identifier' => 'Your account must have a valid 10-digit QR identifier.',
            ]);
        }

        $this->ensureReadableImage($mainImagePath, 'The advert image file could not be found.');

        if ($footerImagePath !== null) {
            $this->ensureReadableImage($footerImagePath, 'The footer advert image file could not be found.');
        }

        $templatePath = public_path('images/not_full_sample.jpeg');
        $this->ensureReadableImage($templatePath, 'The disclaimer template image is missing.');

        $manager = new ImageManager(new Driver);
        $canvasWidth = 1080;
        $canvasHeight = 1350;
        $canvas = $manager->create($canvasWidth, $canvasHeight)->fill('ffffff');

        $headerBottomY = $this->placeHeader(
            $manager,
            $canvas,
            $templatePath,
            $identifier,
            $headerText ?: self::DEFAULT_HEADER,
            $canvasWidth
        );

        $bottomMargin = 18;
        $availableBottom = $canvasHeight - $bottomMargin;

        if ($footerImagePath !== null) {
            $footerHeight = 200;
            $footerTopY = $canvasHeight - $bottomMargin - $footerHeight;
            $footer = $manager->read($footerImagePath);
            $this->cover($footer, $canvasWidth, $footerHeight);
            $canvas->place($footer, 'top-left', 0, $footerTopY);
            $availableBottom = $footerTopY - 18;
        }

        $this->placeMainImage(
            $manager,
            $canvas,
            $mainImagePath,
            $captionText,
            $headerBottomY + 18,
            $availableBottom,
            $canvasWidth
        );

        $saveDirectory = public_path('storage/image_ads/encoded');
        if (! is_dir($saveDirectory) && ! mkdir($saveDirectory, 0755, true) && ! is_dir($saveDirectory)) {
            throw new RuntimeException('The encoded image directory could not be created.');
        }

        $filename = 'stamped_'.Str::uuid().'.png';
        $savePath = $saveDirectory.'/'.$filename;
        $canvas->toPng()->save($savePath);

        return [
            'path' => $savePath,
            'filename' => $filename,
        ];
    }

    private function placeHeader(
        ImageManager $manager,
        Image $canvas,
        string $templatePath,
        string $identifier,
        string $headerText,
        int $canvasWidth
    ): int {
        $header = $manager->read($templatePath);
        $header->scaleDown(width: 920);

        $headerWidth = $header->width();
        $headerHeight = $header->height();
        $sideCircleWidth = $headerHeight;
        $qrSize = (int) round($sideCircleWidth * 0.60);
        $qrCodeImage = (string) QrCode::format('png')
            ->size($qrSize)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($identifier);

        $header->place(
            $manager->read($qrCodeImage),
            'top-left',
            (int) round(($sideCircleWidth - $qrSize) / 2),
            (int) round(($headerHeight - $qrSize) / 2)
        );

        $titleFontPath = $this->fontPath('Roboto_SemiCondensed-SemiBold.ttf', 'Roboto-Bold.ttf');
        $bodyFontPath = $this->fontPath('Roboto_SemiCondensed-Regular.ttf', 'Roboto-Regular.ttf', 'Roboto-Bold.ttf');
        $textPadding = (int) round($headerHeight * 0.05);
        $textLeft = $sideCircleWidth + $textPadding;
        $textRight = $headerWidth - $sideCircleWidth - $textPadding;
        $textCenterX = (int) round(($textLeft + $textRight) / 2);
        $titleY = (int) round($headerHeight * 0.08);
        $underlineY = (int) round($headerHeight * 0.19);
        $bodyY = (int) round($headerHeight * 0.23);

        if ($titleFontPath !== null) {
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

        if ($bodyFontPath !== null) {
            $bodyText = trim((string) preg_replace('/^DISCLAIMER\s*/i', '', trim($headerText)));
            $wrappedBodyText = wordwrap($bodyText, 34, "\n");
            $header->text($wrappedBodyText, $textCenterX, $bodyY, function ($font) use ($bodyFontPath, $headerHeight) {
                $font->file($bodyFontPath);
                $font->size((int) round($headerHeight * 0.09));
                $font->color('000000');
                $font->align('center');
                $font->valign('top');
                $font->lineHeight(1.30);
            });
        }

        $headerTopY = 18;
        $headerX = (int) round(($canvasWidth - $headerWidth) / 2);
        $canvas->place($header, 'top-left', $headerX, $headerTopY);

        return $headerTopY + $headerHeight;
    }

    private function placeMainImage(
        ImageManager $manager,
        Image $canvas,
        string $mainImagePath,
        ?string $captionText,
        int $availableTop,
        int $availableBottom,
        int $canvasWidth
    ): void {
        $bodyFontPath = $this->fontPath('Roboto_SemiCondensed-Regular.ttf', 'Roboto-Regular.ttf', 'Roboto-Bold.ttf');
        $sidePadding = 24;
        $captionSpace = ($captionText && $bodyFontPath !== null) ? 70 : 0;
        $targetX = $sidePadding;
        $targetY = $availableTop + $captionSpace;
        $targetWidth = $canvasWidth - ($sidePadding * 2);
        $targetHeight = $availableBottom - $availableTop - $captionSpace;

        if ($targetHeight < 1) {
            throw new RuntimeException('The encoded image layout does not have enough space for the advert.');
        }

        if ($captionText && $bodyFontPath !== null) {
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

        $mainImage = $manager->read($mainImagePath);
        $this->cover($mainImage, $targetWidth, $targetHeight);
        $canvas->place($mainImage, 'top-left', $targetX, $targetY);
    }

    private function cover(Image $image, int $targetWidth, int $targetHeight): void
    {
        $scale = max($targetWidth / $image->width(), $targetHeight / $image->height());
        $resizedWidth = (int) ceil($image->width() * $scale);
        $resizedHeight = (int) ceil($image->height() * $scale);
        $image->resize($resizedWidth, $resizedHeight);
        $image->crop(
            $targetWidth,
            $targetHeight,
            (int) max(0, floor(($resizedWidth - $targetWidth) / 2)),
            (int) max(0, floor(($resizedHeight - $targetHeight) / 2))
        );
    }

    private function ensureReadableImage(string $path, string $message): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['image' => $message]);
        }
    }

    private function fontPath(string ...$filenames): ?string
    {
        foreach ($filenames as $filename) {
            $path = public_path('fonts/'.$filename);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
