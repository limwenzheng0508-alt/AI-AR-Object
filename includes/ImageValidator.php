<?php

declare(strict_types=1);

/**
 * Validates and decodes Base64 camera images.
 */
final class ImageValidator
{
    public const MAX_BYTES = 4_000_000; // ~4MB decoded

    /**
     * @return array{ok:bool,error?:string,binary?:string,mime?:string}
     */
    public static function fromPayload(string $imageField): array
    {
        $raw = trim($imageField);
        if ($raw === '') {
            return ['ok' => false, 'error' => 'Empty image.'];
        }

        // data:image/jpeg;base64,....
        $mime = 'image/jpeg';
        if (preg_match('#^data:(image/(?:jpeg|jpg|png|webp));base64,#i', $raw, $m)) {
            $mime = strtolower($m[1]);
            if ($mime === 'image/jpg') {
                $mime = 'image/jpeg';
            }
            $raw = substr($raw, strpos($raw, ',') + 1);
        }

        $raw = preg_replace('/\s+/', '', $raw) ?? $raw;
        if ($raw === '' || !preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $raw)) {
            return ['ok' => false, 'error' => 'Invalid Base64 image.'];
        }

        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '') {
            return ['ok' => false, 'error' => 'Unable to decode image.'];
        }

        if (strlen($binary) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Image is too large.'];
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            return ['ok' => false, 'error' => 'Invalid image data.'];
        }

        $detected = $info['mime'] ?? '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($detected, $allowed, true)) {
            return ['ok' => false, 'error' => 'Unsupported image type.'];
        }

        return [
            'ok' => true,
            'binary' => $binary,
            'mime' => $detected,
        ];
    }
}
