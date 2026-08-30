<?php

declare(strict_types=1);

namespace PhotoInbox;

/**
 * Trägt das Upload-Ergebnis über den Redirect hinweg - genau einmal.
 */
final class Flash
{
    private const KEY = 'photo_inbox.report';

    public static function set(UploadReport $report): void
    {
        $_SESSION[self::KEY] = $report->toArray();
    }

    public static function take(): ?UploadReport
    {
        $data = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);

        return is_array($data) ? UploadReport::fromArray($data) : null;
    }
}
