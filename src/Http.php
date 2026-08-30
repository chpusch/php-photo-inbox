<?php

declare(strict_types=1);

namespace PhotoInbox;

final class Http
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('photo_inbox');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::basePath(),
            'httponly' => true,
            'secure'   => self::isHttps(),
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function sendSecurityHeaders(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
        header(
            "Content-Security-Policy: default-src 'none'; "
            . "img-src 'self' blob: data:; "
            . "style-src 'self'; "
            . "script-src 'self'; "
            . "connect-src 'self'; "
            . "form-action 'self'; "
            . "base-uri 'none'; "
            . "frame-ancestors 'none'"
        );

        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Das Formular enthält ein CSRF-Token - nichts davon gehört in einen Cache.
        header('Cache-Control: no-store, private');
    }

    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function wantsJson(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
    }

    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function redirect(string $location): never
    {
        // 303 erzwingt beim Client ein GET - genau das Ziel von Post/Redirect/Get.
        header('Location: ' . $location, true, 303);
        exit;
    }

    /**
     * Eigene URL ohne Query-String. Bewusst aus dem Skriptpfad gebildet und
     * nicht aus REQUEST_URI, damit kein Fremdinhalt in den Location-Header
     * gelangt.
     */
    public static function selfUrl(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $script = str_replace(["\r", "\n"], '', $script);

        return $script === '' ? '/' : $script;
    }

    private static function basePath(): string
    {
        $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));

        return rtrim($dir, '/') . '/';
    }
}
