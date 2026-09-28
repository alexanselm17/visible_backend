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
    private const SMART_OVERLAY_LAYOUT = 'smart_overlay';

    public function __construct(private readonly InvisibleImageWatermarkService $watermarks)
    {
    }

    /**
     * @return array<int, string>
     */
    public static function supportedLayouts(): array
    {
        return [
            ...array_keys(self::splitLayoutConfigs()),
            self::SMART_OVERLAY_LAYOUT,
        ];
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
        if ($layout === self::SMART_OVERLAY_LAYOUT) {
            $this->placeSmartOverlayLayout(
                $manager,
                $canvas,
                $mainImagePath,
                $footerImagePath,
                $canvasWidth,
                $canvasHeight
            );

            return;
        }

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

    private function placeSmartOverlayLayout(
        ImageManager $manager,
        Image $canvas,
        string $mainImagePath,
        string $advertImagePath,
        int $canvasWidth,
        int $canvasHeight
    ): void {
        $backdrop = $manager->create($canvasWidth, $canvasHeight)->fill('0b1020');
        $canvas->place($backdrop, 'top-left', 0, 0);

        $userImage = $manager->read($mainImagePath);
        $this->placeContainedImageOnCanvas(
            $canvas,
            $userImage,
            0,
            0,
            $canvasWidth,
            $canvasHeight
        );

        $this->placeBottomFade($manager, $canvas, $canvasWidth, $canvasHeight);

        $advert = $this->readImageWithTransparentTrim($manager, $advertImagePath);
        [$advertWidth, $advertHeight] = $this->resizeImageToFit(
            $advert,
            (int) floor($canvasWidth * 0.56),
            310
        );

        $cardPaddingX = 32;
        $cardPaddingY = 28;
        $cardWidth = $advertWidth + ($cardPaddingX * 2);
        $cardHeight = $advertHeight + ($cardPaddingY * 2) + 20;
        $cardX = (int) floor(($canvasWidth - $cardWidth) / 2);
        $cardY = $canvasHeight - $cardHeight - 130;
        $advertLuminance = $this->averageOpaqueLuminance($advertImagePath);
        $isDarkAdvert = $advertLuminance < 95;
        $cardColor = $isDarkAdvert ? 'f59e0b' : '111827';
        $borderColor = $isDarkAdvert ? 'fbbf24' : '475569';
        $accentColor = $isDarkAdvert ? '111827' : 'f59e0b';
        $cardOpacity = $isDarkAdvert ? 62 : 58;

        $shadow = $manager->create($cardWidth, $cardHeight)->fill('000000');
        $canvas->place($shadow, 'top-left', $cardX, $cardY + 16, 32);

        $card = $manager->create($cardWidth, $cardHeight)->fill($cardColor);
        $canvas->place($card, 'top-left', $cardX, $cardY, $cardOpacity);
        $canvas->drawRectangle($cardX, $cardY, function ($rectangle) use ($cardWidth, $cardHeight, $borderColor) {
            $rectangle->size($cardWidth, $cardHeight);
            $rectangle->border($borderColor, 2);
        });

        $accent = $manager->create($cardWidth - 54, 7)->fill($accentColor);
        $canvas->place($accent, 'top-left', $cardX + 27, $cardY + 20, 82);

        $shine = $manager->create($cardWidth - 70, 1)->fill('ffffff');
        $canvas->place($shine, 'top-left', $cardX + 35, $cardY + 40, 18);

        $canvas->place($advert, 'top-left', $cardX + $cardPaddingX, $cardY + $cardPaddingY + 20);
    }

    private function placeBottomFade(
        ImageManager $manager,
        Image $canvas,
        int $canvasWidth,
        int $canvasHeight
    ): void {
        $startY = (int) floor($canvasHeight * 0.58);
        $steps = 18;
        $stepHeight = (int) ceil(($canvasHeight - $startY) / $steps);

        for ($step = 0; $step < $steps; $step++) {
            $opacity = min(52, 6 + ($step * 3));
            $overlay = $manager->create($canvasWidth, $stepHeight)->fill('000000');
            $canvas->place($overlay, 'top-left', 0, $startY + ($step * $stepHeight), $opacity);
        }
    }

    private function placeContainedImageOnCanvas(
        Image $canvas,
        Image $image,
        int $targetX,
        int $targetY,
        int $targetWidth,
        int $targetHeight
    ): void {
        if ($targetWidth < 1 || $targetHeight < 1) {
            throw new RuntimeException('The encoded image layout does not have enough space for the image.');
        }

        [$resizedWidth, $resizedHeight] = $this->resizeImageToFit($image, $targetWidth, $targetHeight);

        $canvas->place(
            $image,
            'top-left',
            $targetX + (int) floor(($targetWidth - $resizedWidth) / 2),
            $targetY + (int) floor(($targetHeight - $resizedHeight) / 2)
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resizeImageToFit(Image $image, int $targetWidth, int $targetHeight): array
    {
        if ($targetWidth < 1 || $targetHeight < 1) {
            throw new RuntimeException('The encoded image layout does not have enough space for the image.');
        }

        $scale = min($targetWidth / $image->width(), $targetHeight / $image->height());
        $resizedWidth = (int) max(1, floor($image->width() * $scale));
        $resizedHeight = (int) max(1, floor($image->height() * $scale));
        $image->resize($resizedWidth, $resizedHeight);

        return [$resizedWidth, $resizedHeight];
    }

    private function readImageWithTransparentTrim(ImageManager $manager, string $imagePath): Image
    {
        $metadata = getimagesize($imagePath);

        if (($metadata['mime'] ?? null) !== 'image/png') {
            return $manager->read($imagePath);
        }

        $source = imagecreatefrompng($imagePath);

        if ($source === false) {
            return $manager->read($imagePath);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $bounds = $this->opaqueBounds($source, $width, $height);

        if ($bounds === null) {
            imagedestroy($source);

            return $manager->read($imagePath);
        }

        [$minX, $minY, $maxX, $maxY] = $bounds;
        $cropWidth = $maxX - $minX + 1;
        $cropHeight = $maxY - $minY + 1;

        if ($cropWidth >= $width && $cropHeight >= $height) {
            imagedestroy($source);

            return $manager->read($imagePath);
        }

        $cropped = imagecrop($source, [
            'x' => $minX,
            'y' => $minY,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);
        imagedestroy($source);

        if ($cropped === false) {
            return $manager->read($imagePath);
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);

        $tempPath = tempnam(sys_get_temp_dir(), 'visible_advert_');

        if ($tempPath === false) {
            imagedestroy($cropped);

            return $manager->read($imagePath);
        }

        imagepng($cropped, $tempPath);
        imagedestroy($cropped);

        try {
            return $manager->read($tempPath);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * @param resource|\GdImage $source
     *
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    private function opaqueBounds($source, int $width, int $height): ?array
    {
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;

                if ($alpha >= 120) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < 0 || $maxY < 0) {
            return null;
        }

        return [$minX, $minY, $maxX, $maxY];
    }

    private function averageOpaqueLuminance(string $imagePath): float
    {
        $metadata = getimagesize($imagePath);
        $mime = $metadata['mime'] ?? null;
        $source = match ($mime) {
            'image/png' => imagecreatefrompng($imagePath),
            'image/jpeg' => imagecreatefromjpeg($imagePath),
            default => false,
        };

        if ($source === false) {
            return 120.0;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $step = max(1, (int) floor(max($width, $height) / 160));
        $total = 0.0;
        $count = 0;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;

                if ($mime === 'image/png' && $alpha >= 120) {
                    continue;
                }

                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;
                $total += ($red * 0.2126) + ($green * 0.7152) + ($blue * 0.0722);
                $count++;
            }
        }

        imagedestroy($source);

        if ($count === 0) {
            return 120.0;
        }

        return $total / $count;
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
