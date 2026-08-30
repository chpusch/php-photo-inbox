<?php

declare(strict_types=1);

namespace PhotoInbox;

final class RejectedFile
{
    public function __construct(
        public readonly string $originalName,
        public readonly string $reason,
    ) {
    }
}
