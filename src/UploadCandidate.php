<?php

declare(strict_types=1);

namespace PhotoInbox;

/**
 * Eine einzelne Datei aus $_FILES, bevor sie geprüft wurde.
 *
 * Alle Felder sind Nutzereingaben. Insbesondere `$name` ist ein frei wählbarer
 * String vom Client und wird nie zur Pfadbildung verwendet.
 */
final class UploadCandidate
{
    private function __construct(
        public readonly string $name,
        public readonly string $tmpName,
        public readonly int $error,
    ) {
    }

    /**
     * Liefert null für leere Slots - der Browser schickt bei "keine Datei"
     * trotzdem einen Eintrag mit UPLOAD_ERR_NO_FILE.
     */
    public static function fromParts(string $name, string $tmpName, int $error): ?self
    {
        if ($error === UPLOAD_ERR_NO_FILE && trim($name) === '') {
            return null;
        }

        return new self($name, $tmpName, $error);
    }

    /**
     * Der Originalname nur zur Anzeige - ohne Pfadanteile, ohne Steuerzeichen
     * und in der Länge begrenzt. Die Ausgabe wird zusätzlich HTML-escaped.
     */
    public function displayName(): string
    {
        $name = str_replace(['/', '\\', "\0"], ' ', $this->name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = trim(basename($name));

        if ($name === '' || $name === '.' || $name === '..') {
            return 'Unbenannte Datei';
        }

        return mb_substr($name, 0, 120);
    }
}
