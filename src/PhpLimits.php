<?php

declare(strict_types=1);

namespace PhotoInbox;

/**
 * Hilfsfunktionen rund um die PHP-Upload-Limits.
 */
final class PhpLimits
{
    /**
     * Wandelt eine ini-Groessenangabe ("14M", "2G", "1024K") in Bytes um.
     * Liefert 0, wenn die Angabe leer oder unbegrenzt ist.
     */
    public static function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return 0;
        }

        $suffix = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        if ($number <= 0) {
            return 0;
        }

        return match ($suffix) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    /**
     * Wurde der Request verworfen, weil er groesser als post_max_size war?
     * PHP liefert dann leere $_POST/$_FILES trotz POST-Body.
     */
    public static function postSizeExceeded(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        if ($_POST !== [] || $_FILES !== []) {
            return false;
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxSize   = self::toBytes((string) ini_get('post_max_size'));

        return $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024 * 1024), 1, ',', '.') . ' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.') . ' KB';
        }

        return $bytes . ' Bytes';
    }
}
