<?php

declare(strict_types=1);

namespace PhotoInbox;

final class SavedFile
{
    public function __construct(
        public readonly string $originalName,
        public readonly string $storedName,
        public readonly int $bytes,
        public readonly ImageFormat $format,
    ) {
    }
}
