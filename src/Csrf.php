<?php

declare(strict_types=1);

namespace PhotoInbox;

use RuntimeException;

/**
 * Synchronizer-Token gegen Cross-Site Request Forgery.
 */
final class Csrf
{
    private const SESSION_KEY = 'photo_inbox.csrf';
    public const FIELD = '_csrf';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session muss gestartet sein, bevor ein CSRF-Token erzeugt wird.');
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function isValid(mixed $candidate): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($expected)
            && is_string($candidate)
            && $candidate !== ''
            && hash_equals($expected, $candidate);
    }
}
