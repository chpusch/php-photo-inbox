<?php

declare(strict_types=1);

namespace PhotoInbox;

use InvalidArgumentException;

/**
 * Getypte, validierte Sicht auf config.php.
 */
final class Config
{
    /** @param list<string> $allowedMimeTypes */
    private function __construct(
        public readonly string $uploadDir,
        public readonly int $maxFileBytes,
        public readonly int $maxFilesPerRequest,
        public readonly array $allowedMimeTypes,
        public readonly bool $keepOriginalName,
        public readonly int $dirPermissions,
        public readonly int $filePermissions,
        public readonly string $appName,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $uploadDir = (string) ($data['upload_dir'] ?? '');
        if ($uploadDir === '') {
            throw new InvalidArgumentException('config.php: "upload_dir" darf nicht leer sein.');
        }

        $allowed = [];
        foreach ((array) ($data['allowed_mime_types'] ?? []) as $mime) {
            $mime = strtolower(trim((string) $mime));
            if ($mime !== '' && ImageFormat::tryFrom($mime) !== null) {
                $allowed[] = $mime;
            }
        }
        if ($allowed === []) {
            throw new InvalidArgumentException('config.php: "allowed_mime_types" enthaelt kein unterstuetztes Format.');
        }

        return new self(
            uploadDir: rtrim($uploadDir, "/\\"),
            maxFileBytes: max(1, (int) ($data['max_file_bytes'] ?? 14 * 1024 * 1024)),
            maxFilesPerRequest: max(1, (int) ($data['max_files_per_request'] ?? 25)),
            allowedMimeTypes: array_values(array_unique($allowed)),
            keepOriginalName: (bool) ($data['keep_original_name'] ?? true),
            dirPermissions: (int) ($data['dir_permissions'] ?? 0750),
            filePermissions: (int) ($data['file_permissions'] ?? 0640),
            appName: trim((string) ($data['app_name'] ?? 'Foto-Postfach')) ?: 'Foto-Postfach',
        );
    }

    /** @return list<ImageFormat> */
    public function allowedFormats(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $mime): ?ImageFormat => ImageFormat::tryFrom($mime),
            $this->allowedMimeTypes,
        )));
    }

    /**
     * Tatsaechlich wirksames Limit pro Datei: das Minimum aus der eigenen
     * Konfiguration und den PHP-Limits, die zur Laufzeit nicht aenderbar sind.
     */
    public function effectiveMaxFileBytes(): int
    {
        $limits = [$this->maxFileBytes];

        foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
            $bytes = PhpLimits::toBytes((string) ini_get($directive));
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }

        return min($limits);
    }
}
