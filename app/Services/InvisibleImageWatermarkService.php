<?php

namespace App\Services;

use RuntimeException;

class InvisibleImageWatermarkService
{
    private const MAGIC = 'VDM1';

    private const BIT_REPEAT = 5;

    private const HMAC_BYTES = 8;

    private const MAX_CONTENT_BYTES = 2048;

    public function embed(string $imagePath, string $content): void
    {
        if ($content === '' || strlen($content) > self::MAX_CONTENT_BYTES) {
            throw new RuntimeException('The invisible watermark content must be between 1 and 2,048 bytes.');
        }

        $image = $this->readImage($imagePath);
        $width = imagesx($image);
        $height = imagesy($image);
        $capacity = $width * $height;
        $bits = $this->bytesToBits($this->payload($content));
        $requiredPixels = count($bits) * self::BIT_REPEAT;

        if ($requiredPixels >= $capacity) {
            imagedestroy($image);
            throw new RuntimeException('The image is too small for the invisible watermark payload.');
        }

        imagepalettetotruecolor($image);
        imagesavealpha($image, true);

        foreach ($bits as $bitIndex => $bit) {
            for ($repeat = 0; $repeat < self::BIT_REPEAT; $repeat++) {
                $position = $this->positionForSequence(($bitIndex * self::BIT_REPEAT) + $repeat, $capacity);
                $x = $position % $width;
                $y = intdiv($position, $width);
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = ($rgba & 0xFF & 0xFE) | $bit;
                $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);

                imagesetpixel($image, $x, $y, $color);
            }
        }

        imagepng($image, $imagePath);
        imagedestroy($image);
    }

    public function extract(string $imagePath): ?string
    {
        if (! is_file($imagePath) || ! is_readable($imagePath)) {
            return null;
        }

        $image = $this->readImage($imagePath);
        $header = $this->readBytes($image, 8);

        if ($header === null || substr($header, 0, 4) !== self::MAGIC) {
            imagedestroy($image);
            return null;
        }

        $length = unpack('N', substr($header, 4, 4))[1] ?? 0;
        if ($length < 1 || $length > self::MAX_CONTENT_BYTES) {
            imagedestroy($image);
            return null;
        }

        $payload = $this->readBytes($image, 8 + $length + self::HMAC_BYTES);
        imagedestroy($image);

        if ($payload === null || substr($payload, 0, 4) !== self::MAGIC) {
            return null;
        }

        $content = substr($payload, 8, $length);
        $signature = substr($payload, 8 + $length, self::HMAC_BYTES);

        return hash_equals($this->signature($content), $signature) ? $content : null;
    }

    private function payload(string $content): string
    {
        return self::MAGIC.pack('N', strlen($content)).$content.$this->signature($content);
    }

    private function signature(string $content): string
    {
        return substr(hash_hmac('sha256', self::MAGIC.$content, $this->signingKey(), true), 0, self::HMAC_BYTES);
    }

    /**
     * @return list<int>
     */
    private function bytesToBits(string $bytes): array
    {
        $bits = [];

        for ($byteIndex = 0, $byteCount = strlen($bytes); $byteIndex < $byteCount; $byteIndex++) {
            $byte = ord($bytes[$byteIndex]);

            for ($bit = 7; $bit >= 0; $bit--) {
                $bits[] = ($byte >> $bit) & 1;
            }
        }

        return $bits;
    }

    private function readBytes(\GdImage $image, int $byteCount): ?string
    {
        $width = imagesx($image);
        $capacity = $width * imagesy($image);
        $bitCount = $byteCount * 8;

        if (($bitCount * self::BIT_REPEAT) >= $capacity) {
            return null;
        }

        $bytes = '';
        $currentByte = 0;

        for ($bitIndex = 0; $bitIndex < $bitCount; $bitIndex++) {
            $ones = 0;

            for ($repeat = 0; $repeat < self::BIT_REPEAT; $repeat++) {
                $position = $this->positionForSequence(($bitIndex * self::BIT_REPEAT) + $repeat, $capacity);
                $x = $position % $width;
                $y = intdiv($position, $width);
                $ones += imagecolorat($image, $x, $y) & 1;
            }

            $currentByte = ($currentByte << 1) | ($ones > intdiv(self::BIT_REPEAT, 2) ? 1 : 0);

            if (($bitIndex + 1) % 8 === 0) {
                $bytes .= chr($currentByte);
                $currentByte = 0;
            }
        }

        return $bytes;
    }

    private function positionForSequence(int $sequence, int $capacity): int
    {
        return (137 + ($sequence * $this->stride($capacity))) % $capacity;
    }

    private function stride(int $capacity): int
    {
        $stride = min(7919, max(1, $capacity - 1));

        while ($this->gcd($stride, $capacity) !== 1) {
            $stride += 2;

            if ($stride >= $capacity) {
                $stride = 1;
            }
        }

        return $stride;
    }

    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return abs($a);
    }

    private function readImage(string $imagePath): \GdImage
    {
        $contents = file_get_contents($imagePath);
        $image = $contents !== false ? imagecreatefromstring($contents) : false;

        if (! $image instanceof \GdImage) {
            throw new RuntimeException('The image could not be opened for invisible watermark processing.');
        }

        return $image;
    }

    private function signingKey(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                $key = $decoded;
            }
        }

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required to sign invisible image watermarks.');
        }

        return $key;
    }
}
