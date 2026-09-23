<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use RuntimeException;

class ImageEncoderService
{
    public function __construct(private readonly InvisibleImageWatermarkService $watermarks)
    {
    }

    /**
     * Create a post-ready image containing a hidden signed reference token.
     *
     * @return array{path: string, filename: string}
     */
    public function encode(
        string $mainImagePath,
        string $watermarkContent,
        ?string $footerImagePath = null,
        ?string $headerText = null,
        ?string $captionText = null,
        ?string $visibleProofCode = null
    ): array {
        if ($watermarkContent === '' || strlen($watermarkContent) > 2048) {
            throw ValidationException::withMessages([
                'watermark_content' => 'The invisible watermark content must be between 1 and 2,048 characters.',
            ]);
        }

        $this->ensureReadableImage($mainImagePath, 'The advert image file could not be found.');

        if ($footerImagePath !== null) {
            $this->ensureReadableImage($footerImagePath, 'The footer advert image file could not be found.');
        }

        $manager = new ImageManager(new Driver);
        $canvasWidth = 1080;
        $canvasHeight = 1350;
        $canvas = $manager->create($canvasWidth, $canvasHeight)->fill('ffffff');

        if ($footerImagePath !== null) {
            $this->placeSplitImages($manager, $canvas, $mainImagePath, $footerImagePath, $canvasWidth, $canvasHeight);
        } else {
            $this->placeMainImage(
                $manager,
                $canvas,
                $mainImagePath,
                0,
                $canvasHeight,
                $canvasWidth
            );
        }

        $this->placeSubtleLogo($manager, $canvas, $canvasWidth, $canvasHeight);
        $this->placeVisibleProofCode($canvas, $visibleProofCode, $canvasHeight);

        $saveDirectory = public_path('storage/image_ads/encoded');
        if (! is_dir($saveDirectory) && ! mkdir($saveDirectory, 0755, true) && ! is_dir($saveDirectory)) {
            throw new RuntimeException('The encoded image directory could not be created.');
        }

        $filename = 'stamped_'.Str::uuid().'.png';
        $savePath = $saveDirectory.'/'.$filename;
        $canvas->toPng()->save($savePath);
        $this->watermarks->embed($savePath, $watermarkContent);

        return [
            'path' => $savePath,
            'filename' => $filename,
        ];
    }

    private function placeSubtleLogo(
        ImageManager $manager,
        Image $canvas,
        int $canvasWidth,
        int $canvasHeight
    ): void {
        $templatePath = public_path('images/not_full_sample.jpeg');

        if (! is_file($templatePath)) {
            return;
        }

        $header = $manager->read($templatePath);
        $headerHeight = $header->height();
        $logo = $header->crop(
            $headerHeight,
            $headerHeight,
            max(0, $header->width() - $headerHeight),
            0
        );
        $logo->scale(width: 150);

        $canvas->place(
            $logo,
            'top-left',
            $canvasWidth - $logo->width() - 26,
            $canvasHeight - $logo->height() - 26,
            22
        );
    }

    private function placeVisibleProofCode(
        Image $canvas,
        ?string $visibleProofCode,
        int $canvasHeight
    ): void {
        if ($visibleProofCode === null || trim($visibleProofCode) === '') {
            return;
        }

        $fontPath = $this->fontPath('Roboto_SemiCondensed-SemiBold.ttf', 'Roboto-Bold.ttf');
        $text = strtoupper(trim($visibleProofCode));
        $x = 34;
        $y = $canvasHeight - 54;

        $canvas->text($text, $x + 2, $y + 2, function ($font) use ($fontPath) {
            if ($fontPath !== null) {
                $font->file($fontPath);
            }
            $font->size(30);
            $font->color('000000');
            $font->align('left');
            $font->valign('top');
        });

        $canvas->text($text, $x, $y, function ($font) use ($fontPath) {
            if ($fontPath !== null) {
                $font->file($fontPath);
            }
            $font->size(30);
            $font->color('ffffff');
            $font->align('left');
            $font->valign('top');
        });
    }

    private function placeSplitImages(
        ImageManager $manager,
        Image $canvas,
        string $mainImagePath,
        string $footerImagePath,
        int $canvasWidth,
        int $canvasHeight
    ): void {
        $outerPadding = 24;
        $sectionGap = 18;
        $footerHeight = 430;
        $mainHeight = $canvasHeight - $footerHeight - $sectionGap;
        $targetWidth = $canvasWidth - ($outerPadding * 2);

        $this->placeContainedImage(
            $manager,
            $canvas,
            $mainImagePath,
            $outerPadding,
            $outerPadding,
            $targetWidth,
            $mainHeight - ($outerPadding * 2),
            'f8f8f8'
        );

        $this->placeContainedImage(
            $manager,
            $canvas,
            $footerImagePath,
            $outerPadding,
            $mainHeight + $sectionGap,
            $targetWidth,
            $footerHeight - $outerPadding,
            'ffffff'
        );
    }

    private function placeContainedImage(
        ImageManager $manager,
        Image $canvas,
        string $imagePath,
        int $targetX,
        int $targetY,
        int $targetWidth,
        int $targetHeight,
        string $background
    ): void {
        if ($targetWidth < 1 || $targetHeight < 1) {
            throw new RuntimeException('The encoded image layout does not have enough space for the image.');
        }

        $area = $manager->create($targetWidth, $targetHeight)->fill($background);
        $image = $manager->read($imagePath);
        $scale = min($targetWidth / $image->width(), $targetHeight / $image->height());
        $resizedWidth = (int) max(1, floor($image->width() * $scale));
        $resizedHeight = (int) max(1, floor($image->height() * $scale));
        $image->resize($resizedWidth, $resizedHeight);

        $area->place(
            $image,
            'top-left',
            (int) floor(($targetWidth - $resizedWidth) / 2),
            (int) floor(($targetHeight - $resizedHeight) / 2)
        );

        $canvas->place($area, 'top-left', $targetX, $targetY);
    }

    private function placeMainImage(
        ImageManager $manager,
        Image $canvas,
        string $mainImagePath,
        int $availableTop,
        int $availableBottom,
        int $canvasWidth
    ): void {
        $sidePadding = 24;
        $captionSpace = 0;
        $targetX = $sidePadding;
        $targetY = $availableTop + $captionSpace;
        $targetWidth = $canvasWidth - ($sidePadding * 2);
        $targetHeight = $availableBottom - $availableTop - $captionSpace;

        if ($targetHeight < 1) {
            throw new RuntimeException('The encoded image layout does not have enough space for the advert.');
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
