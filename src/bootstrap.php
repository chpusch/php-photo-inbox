<?php

declare(strict_types=1);

namespace PhotoInbox;

/**
 * Minimaler PSR-4-Autoloader für den Namespace PhotoInbox.
 * Das Projekt soll sich per FTP hochladen lassen - ohne Composer-Schritt.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path     = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

/**
 * Kurzform für die HTML-Ausgabe. Jede dynamische Ausgabe in den Templates
 * läuft hierdurch - insbesondere die vom Client stammenden Dateinamen.
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
