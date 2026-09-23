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
    private const DEFAULT_LAYOUT = 'story';

    public function __construct(private readonly InvisibleImageWatermarkService $watermarks)
    {
    }

    /**
     * @return array<int, string>
     */
    public static function supportedLayouts(): array
    {
        return array_keys(self::splitLayoutConfigs());
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
        ?string $visibleProofCode = null,
        ?string $layout = null
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
        $layout = $this->normalizeLayout($layout);

        if ($footerImagePath !== null) {
            $this->placeSplitImages($manager, $canvas, $mainImagePath, $footerImagePath, $canvasWidth, $canvasHeight, $layout);
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
        $logo->scale(width: 104);

        $canvas->place(
            $logo,
            'top-left',
            $canvasWidth - $logo->width() - 28,
            $canvasHeight - $logo->height() - 28,
            16
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
        int $canvasHeight,
        string $layout
    ): void {
        $config = self::splitLayoutConfigs()[$layout];
        $sectionGap = 18;
        $mainHeight = $config['main_height'];
        $footerTop = $mainHeight + $sectionGap;
        $footerHeight = $canvasHeight - $footerTop;

        $this->placeImagePanel(
            $manager,
            $canvas,
            $mainImagePath,
            0,
            0,
            $canvasWidth,
            $mainHeight,
            $config['main_padding'],
            $config['main_blur'],
            $config['main_overlay']
        );

        $this->placeImagePanel(
            $manager,
            $canvas,
            $footerImagePath,
            0,
            $footerTop,
            $canvasWidth,
            $footerHeight,
            $config['advert_padding'],
            $config['advert_blur'],
            $config['advert_overlay']
        );
    }

    /**
     * @return array<string, array{
     *     main_height: int,
     *     main_padding: int,
     *     main_blur: int,
     *     main_overlay: int,
     *     advert_padding: int,
     *     advert_blur: int,
     *     advert_overlay: int
     * }>
     */
    private static function splitLayoutConfigs(): array
    {
        return [
            // Best default for WhatsApp/status screenshots: big user proof image, advert clearly shown below.
            'story' => [
                'main_height' => 820,
                'main_padding' => 28,
                'main_blur' => 18,
                'main_overlay' => 18,
                'advert_padding' => 44,
                'advert_blur' => 14,
                'advert_overlay' => 10,
            ],

            // Gives the user image and advert more equal visual weight.
            'balanced' => [
                'main_height' => 700,
                'main_padding' => 34,
                'main_blur' => 18,
                'main_overlay' => 16,
                'advert_padding' => 40,
                'advert_blur' => 16,
                'advert_overlay' => 8,
            ],

            // Makes the advert feel more premium/prominent while still keeping the user image visible.
            'ad_focus' => [
                'main_height' => 590,
                'main_padding' => 34,
                'main_blur' => 20,
                'main_overlay' => 20,
                'advert_padding' => 36,
                'advert_blur' => 14,
                'advert_overlay' => 8,
            ],
        ];
    }

    private function normalizeLayout(?string $layout): string
    {
        $layout = trim((string) $layout);

        if ($layout === '') {
            return self::DEFAULT_LAYOUT;
        }

        if (! in_array($layout, self::supportedLayouts(), true)) {
            throw ValidationException::withMessages([
                'layout' => 'The selected image layout is invalid.',
            ]);
        }

        return $layout;
    }

    private function placeImagePanel(
        ImageManager $manager,
        Image $canvas,
        string $imagePath,
        int $panelX,
        int $panelY,
        int $panelWidth,
        int $panelHeight,
        int $foregroundPadding,
        int $backgroundBlur,
        int $backgroundOverlayOpacity
    ): void {
        if ($panelWidth < 1 || $panelHeight < 1) {
            throw new RuntimeException('The encoded image layout does not have enough space for the image.');
        }

        $panel = $manager->create($panelWidth, $panelHeight)->fill('f4f4f4');
        $background = $manager->read($imagePath);
        $this->cover($background, $panelWidth, $panelHeight);
        $background->blur($backgroundBlur);
        $panel->place($background, 'top-left', 0, 0);

        if ($backgroundOverlayOpacity > 0) {
            $overlay = $manager->create($panelWidth, $panelHeight)->fill('000000');
            $panel->place($overlay, 'top-left', 0, 0, $backgroundOverlayOpacity);
        }

        $targetWidth = $panelWidth - ($foregroundPadding * 2);
        $targetHeight = $panelHeight - ($foregroundPadding * 2);

        if ($targetWidth < 1 || $targetHeight < 1) {
            throw new RuntimeException('The encoded image layout does not have enough space for the image.');
        }

        $image = $manager->read($imagePath);
        $scale = min($targetWidth / $image->width(), $targetHeight / $image->height());
        $resizedWidth = (int) max(1, floor($image->width() * $scale));
        $resizedHeight = (int) max(1, floor($image->height() * $scale));
        $image->resize($resizedWidth, $resizedHeight);

        $panel->place(
            $image,
            'top-left',
            $foregroundPadding + (int) floor(($targetWidth - $resizedWidth) / 2),
            $foregroundPadding + (int) floor(($targetHeight - $resizedHeight) / 2)
        );

        $canvas->place($panel, 'top-left', $panelX, $panelY);
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
