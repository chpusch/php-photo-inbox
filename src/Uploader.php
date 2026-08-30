<?php

declare(strict_types=1);

namespace PhotoInbox;

use RuntimeException;

final class Uploader
{
    public function __construct(
        private readonly Config $config,
        private readonly UploadDirectory $directory,
        private readonly FormatDetector $detector,
    ) {
    }

    /** @param array<string, mixed> $filesField Der Eintrag aus $_FILES */
    public function handle(array $filesField): UploadReport
    {
        $candidates = $this->normalize($filesField);

        if ($candidates === []) {
            return UploadReport::failure('Es wurde keine Datei ausgewählt.');
        }

        if (count($candidates) > $this->config->maxFilesPerRequest) {
            return UploadReport::failure(sprintf(
                'Zu viele Dateien auf einmal: %d ausgewählt, maximal %d pro Vorgang.',
                count($candidates),
                $this->config->maxFilesPerRequest,
            ));
        }

        try {
            $targetDir = $this->directory->prepare();
        } catch (RuntimeException $e) {
            return UploadReport::failure($e->getMessage());
        }

        $saved    = [];
        $rejected = [];

        foreach ($candidates as $candidate) {
            $result = $this->store($candidate, $targetDir);
            if ($result instanceof SavedFile) {
                $saved[] = $result;
            } else {
                $rejected[] = $result;
            }
        }

        return new UploadReport($saved, $rejected);
    }

    private function store(UploadCandidate $candidate, string $targetDir): SavedFile|RejectedFile
    {
        $displayName = $candidate->displayName();

        if ($candidate->error !== UPLOAD_ERR_OK) {
            return new RejectedFile($displayName, $this->describeUploadError($candidate->error));
        }

        // Schützt davor, dass eine beliebige Serverdatei untergeschoben wird.
        if (!is_uploaded_file($candidate->tmpName)) {
            return new RejectedFile($displayName, 'Die Datei stammt nicht aus einem gültigen Upload.');
        }

        $bytes = (int) @filesize($candidate->tmpName);
        if ($bytes <= 0) {
            return new RejectedFile($displayName, 'Die Datei ist leer.');
        }

        $limit = $this->config->effectiveMaxFileBytes();
        if ($bytes > $limit) {
            return new RejectedFile($displayName, sprintf(
                'Die Datei ist mit %s zu groß (erlaubt sind %s).',
                PhpLimits::formatBytes($bytes),
                PhpLimits::formatBytes($limit),
            ));
        }

        $format = $this->detector->detect($candidate->tmpName);
        if ($format === null || !in_array($format->value, $this->config->allowedMimeTypes, true)) {
            return new RejectedFile($displayName, sprintf(
                'Kein zulässiges Bild. Erlaubt sind: %s.',
                implode(', ', array_map(
                    static fn (ImageFormat $f): string => $f->label(),
                    $this->config->allowedFormats(),
                )),
            ));
        }

        $storedName = $this->buildFileName($displayName, $format);
        $destination = $targetDir . DIRECTORY_SEPARATOR . $storedName;

        if (!@move_uploaded_file($candidate->tmpName, $destination)) {
            return new RejectedFile($displayName, 'Die Datei konnte nicht gespeichert werden.');
        }

        @chmod($destination, $this->config->filePermissions);

        return new SavedFile($displayName, $storedName, $bytes, $format);
    }

    /**
     * Der Zielname wird vollständig serverseitig gebildet: Zeitstempel,
     * Zufallsanteil gegen Kollisionen und eine Endung, die aus dem erkannten
     * Format stammt. Vom Originalnamen überlebt höchstens ein gefilterter,
     * gekürzter Rest - Verzeichniswechsel oder Doppelendungen sind damit
     * konstruktionsbedingt ausgeschlossen.
     */
    private function buildFileName(string $originalName, ImageFormat $format): string
    {
        $parts = [date('Y-m-d_His'), bin2hex(random_bytes(4))];

        if ($this->config->keepOriginalName) {
            $slug = $this->slugify(pathinfo($originalName, PATHINFO_FILENAME));
            if ($slug !== '') {
                $parts[] = $slug;
            }
        }

        return implode('_', $parts) . '.' . $format->extension();
    }

    private function slugify(string $value): string
    {
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);
        $value = trim($value, '-');

        if ($value === '') {
            return '';
        }

        // Nur ASCII in den Dateinamen - das erspart Ärger mit FTP-Clients,
        // fremden Dateisystemen und Zeichensatz-Konvertierungen.
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = (string) preg_replace('/[^A-Za-z0-9-]+/', '-', $value);
        $value = trim((string) preg_replace('/-{2,}/', '-', $value), '-');

        return substr($value, 0, 60);
    }

    private function describeUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                'Die Datei überschreitet das Größenlimit von %s.',
                PhpLimits::formatBytes($this->config->effectiveMaxFileBytes()),
            ),
            UPLOAD_ERR_PARTIAL    => 'Die Übertragung wurde abgebrochen. Bitte erneut versuchen.',
            UPLOAD_ERR_NO_FILE    => 'Es wurde keine Datei übertragen.',
            UPLOAD_ERR_NO_TMP_DIR => 'Serverfehler: kein temporäres Verzeichnis verfügbar.',
            UPLOAD_ERR_CANT_WRITE => 'Serverfehler: die Datei konnte nicht geschrieben werden.',
            UPLOAD_ERR_EXTENSION  => 'Der Upload wurde durch eine PHP-Erweiterung blockiert.',
            default               => 'Unbekannter Fehler beim Upload.',
        };
    }

    /**
     * $_FILES liefert bei mehreren Dateien parallele Arrays. Wir drehen das in
     * eine Liste von Kandidaten und ignorieren leere Slots.
     *
     * @param  array<string, mixed> $field
     * @return list<UploadCandidate>
     */
    private function normalize(array $field): array
    {
        $names = $field['name'] ?? null;

        if (!is_array($names)) {
            $candidate = UploadCandidate::fromParts(
                (string) ($field['name'] ?? ''),
                (string) ($field['tmp_name'] ?? ''),
                (int) ($field['error'] ?? UPLOAD_ERR_NO_FILE),
            );

            return $candidate === null ? [] : [$candidate];
        }

        $candidates = [];
        foreach (array_keys($names) as $index) {
            $candidate = UploadCandidate::fromParts(
                (string) ($names[$index] ?? ''),
                (string) ($field['tmp_name'][$index] ?? ''),
                (int) ($field['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            );

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }
}
