<?php

declare(strict_types=1);

namespace PhotoInbox;

use Throwable;

require __DIR__ . '/src/bootstrap.php';

/* --------------------------------------------------------------------------
 * Konfiguration
 * ------------------------------------------------------------------------ */

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Es fehlt die config.php.\n\nBitte config.example.php nach config.php kopieren und anpassen.\n");
}

try {
    $config = Config::fromArray((array) require $configFile);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Konfigurationsfehler: ' . $e->getMessage() . "\n");
}

/* --------------------------------------------------------------------------
 * Session + Sicherheits-Header
 * ------------------------------------------------------------------------ */

Http::startSession();
Http::sendSecurityHeaders();

$wantsJson = Http::wantsJson();

/* --------------------------------------------------------------------------
 * POST: Upload verarbeiten, danach Redirect (Post/Redirect/Get)
 * ------------------------------------------------------------------------ */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $report = handleUpload($config);

    if ($wantsJson) {
        Http::json($report->toArray(), $report->errors === [] ? 200 : 422);
    }

    // Ohne Redirect würde ein Reload den kompletten Upload wiederholen.
    Flash::set($report);
    Http::redirect(Http::selfUrl());
}

/* --------------------------------------------------------------------------
 * GET: Formular samt Ergebnis der vorherigen Runde rendern
 * ------------------------------------------------------------------------ */

$report = Flash::take();

require __DIR__ . '/templates/upload.php';

/* --------------------------------------------------------------------------
 * Ablauf des Uploads
 * ------------------------------------------------------------------------ */

function handleUpload(Config $config): UploadReport
{
    // Grösser als post_max_size: PHP verwirft den Body, $_POST/$_FILES sind
    // leer - inklusive des CSRF-Tokens. Diesen Fall zuerst abfangen.
    if (PhpLimits::postSizeExceeded()) {
        return UploadReport::failure(sprintf(
            'Der Upload war insgesamt zu groß. Das Serverlimit liegt bei %s pro Vorgang.',
            PhpLimits::formatBytes(PhpLimits::toBytes((string) ini_get('post_max_size'))),
        ));
    }

    if (!Csrf::isValid($_POST[Csrf::FIELD] ?? null)) {
        return UploadReport::failure('Die Sitzung ist abgelaufen. Bitte die Seite neu laden und erneut versuchen.');
    }

    if (!isset($_FILES['files']) || !is_array($_FILES['files'])) {
        return UploadReport::failure('Es wurde keine Datei ausgewählt.');
    }

    $uploader = new Uploader($config, new UploadDirectory($config), new FormatDetector());

    return $uploader->handle($_FILES['files']);
}
