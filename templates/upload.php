<?php

declare(strict_types=1);

namespace PhotoInbox;

/**
 * @var Config            $config
 * @var UploadReport|null $report
 */

$maxBytes    = $config->effectiveMaxFileBytes();
$maxLabel    = PhpLimits::formatBytes($maxBytes);
$formats     = array_map(static fn (ImageFormat $f): string => $f->label(), $config->allowedFormats());
$formatList  = implode(', ', $formats);
$acceptAttr  = implode(',', array_map(static fn (ImageFormat $f): string => $f->value, $config->allowedFormats()));
$assetsHash  = substr(hash('crc32b', (string) @filemtime(__DIR__ . '/../assets/style.css')), 0, 8);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title><?= e($config->appName) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?= e($assetsHash) ?>">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%B7%3C/text%3E%3C/svg%3E">
</head>
<body>
<div class="shell">
    <header class="masthead">
        <span class="masthead__badge" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h1.7a1 1 0 0 0 .83-.45l.94-1.4A1 1 0 0 1 9.8 3.7h4.4a1 1 0 0 1 .83.45l.94 1.4a1 1 0 0 0 .83.45h1.7A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/>
                <circle cx="12" cy="12.2" r="3.4"/>
            </svg>
        </span>
        <h1><?= e($config->appName) ?></h1>
        <p class="masthead__sub">Bilder auswählen oder hierher ziehen &ndash; der Rest passiert automatisch.</p>
    </header>

    <main class="card">
        <?php if ($report !== null && !$report->isEmpty()): ?>
            <div class="results" role="status" aria-live="polite">
                <?php foreach ($report->errors as $error): ?>
                    <div class="alert alert--error">
                        <span class="alert__icon" aria-hidden="true">!</span>
                        <p><?= e($error) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php if ($report->saved !== []): ?>
                    <div class="alert alert--success">
                        <span class="alert__icon" aria-hidden="true">&check;</span>
                        <div>
                            <p><strong><?= count($report->saved) ?></strong>
                                <?= count($report->saved) === 1 ? 'Bild wurde' : 'Bilder wurden' ?> gespeichert.</p>
                            <ul class="filelist">
                                <?php foreach ($report->saved as $file): ?>
                                    <li>
                                        <span class="filelist__name"><?= e($file->originalName) ?></span>
                                        <span class="tag"><?= e($file->format->label()) ?></span>
                                        <span class="filelist__meta"><?= e(PhpLimits::formatBytes($file->bytes)) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($report->rejected !== []): ?>
                    <div class="alert alert--warning">
                        <span class="alert__icon" aria-hidden="true">&times;</span>
                        <div>
                            <p><strong><?= count($report->rejected) ?></strong>
                                <?= count($report->rejected) === 1 ? 'Datei wurde' : 'Dateien wurden' ?> abgelehnt.</p>
                            <ul class="filelist">
                                <?php foreach ($report->rejected as $file): ?>
                                    <li>
                                        <span class="filelist__name"><?= e($file->originalName) ?></span>
                                        <span class="filelist__meta"><?= e($file->reason) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form class="uploader" method="post" action="" enctype="multipart/form-data"
              data-max-bytes="<?= e($maxBytes) ?>"
              data-max-files="<?= e($config->maxFilesPerRequest) ?>"
              data-accept="<?= e($acceptAttr) ?>">
            <input type="hidden" name="<?= e(Csrf::FIELD) ?>" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= e($maxBytes) ?>">

            <div class="dropzone" data-dropzone>
                <input class="dropzone__input" type="file" name="files[]" id="files"
                       accept="<?= e($acceptAttr) ?>" multiple
                       aria-label="Bilder auswählen" aria-describedby="upload-hint">
                <span class="dropzone__art" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5"/>
                        <path d="M4 15v2.5A2.5 2.5 0 0 0 6.5 20h11a2.5 2.5 0 0 0 2.5-2.5V15"/>
                    </svg>
                </span>
                <span class="dropzone__title">Bilder hierher ziehen</span>
                <span class="dropzone__hint" id="upload-hint">
                    oder klicken zum Auswählen &middot; max. <?= e($maxLabel) ?> pro Bild
                    &middot; <?= e($formatList) ?>
                </span>
            </div>

            <ul class="queue" data-queue hidden></ul>

            <div class="progress" data-progress hidden>
                <div class="progress__bar"><span data-progress-bar></span></div>
                <p class="progress__label" data-progress-label>Upload läuft &hellip;</p>
            </div>

            <div class="actions">
                <button class="btn btn--primary" type="submit" data-submit>
                    <span data-submit-label>Hochladen</span>
                </button>
                <button class="btn btn--ghost" type="button" data-clear hidden>Auswahl leeren</button>
            </div>
        </form>
    </main>

    <footer class="footnote">
        <p>Maximal <?= e($config->maxFilesPerRequest) ?> Bilder pro Vorgang &middot; erlaubt: <?= e($formatList) ?></p>
    </footer>
</div>
<script src="assets/app.js?v=<?= e($assetsHash) ?>" defer></script>
</body>
</html>
