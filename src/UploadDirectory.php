<?php

declare(strict_types=1);

namespace PhotoInbox;

use RuntimeException;

/**
 * Legt den Zielordner an und schottet ihn ab, falls er im Document Root liegt.
 */
final class UploadDirectory
{
    public function __construct(private readonly Config $config)
    {
    }

    /** @throws RuntimeException wenn der Ordner nicht nutzbar ist */
    public function prepare(): string
    {
        $dir = $this->config->uploadDir;

        if (!is_dir($dir) && !@mkdir($dir, $this->config->dirPermissions, true) && !is_dir($dir)) {
            throw new RuntimeException('Der Upload-Ordner konnte nicht angelegt werden. Bitte Pfad und Schreibrechte pruefen.');
        }

        $real = realpath($dir);
        if ($real === false) {
            throw new RuntimeException('Der Upload-Ordner ist nicht erreichbar.');
        }

        if (!is_writable($real)) {
            throw new RuntimeException('Der Upload-Ordner ist nicht beschreibbar. Bitte die Verzeichnisrechte pruefen.');
        }

        $this->denyWebAccess($real);

        return $real;
    }

    /**
     * Liegt der Ordner unterhalb des Document Root, ist er ohne weiteres per
     * URL erreichbar. Wir sperren ihn dann per .htaccess und verhindern
     * zugleich, dass dort abgelegte Dateien je als Skript ausgefuehrt werden.
     */
    private function denyWebAccess(string $dir): void
    {
        if (!$this->isInsideDocumentRoot($dir)) {
            return;
        }

        $htaccess = $dir . '/.htaccess';
        if (is_file($htaccess)) {
            return;
        }

        $rules = <<<'HTACCESS'
        # Automatisch erzeugt vom Foto-Postfach - nicht loeschen.
        # Sperrt den direkten Web-Zugriff auf die hochgeladenen Bilder.
        <IfModule mod_authz_core.c>
            Require all denied
        </IfModule>
        <IfModule !mod_authz_core.c>
            Order allow,deny
            Deny from all
        </IfModule>

        # Zweite Verteidigungslinie: hier wird niemals Code ausgefuehrt.
        # php_flag kennt nur mod_php - ungeschuetzt wuerde es unter PHP-FPM
        # einen 500er ausloesen, daher die IfModule-Klammern.
        <IfModule mod_php.c>
            php_flag engine off
        </IfModule>
        <IfModule mod_php7.c>
            php_flag engine off
        </IfModule>
        <IfModule mod_php5.c>
            php_flag engine off
        </IfModule>
        RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl
        RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar

        HTACCESS;

        @file_put_contents($htaccess, $rules, LOCK_EX);
        @chmod($htaccess, $this->config->filePermissions);
    }

    private function isInsideDocumentRoot(string $dir): bool
    {
        $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($docRoot === false || $docRoot === '') {
            // Ohne bekannten Document Root gehen wir vom unguenstigeren Fall aus.
            return true;
        }

        return str_starts_with($dir . DIRECTORY_SEPARATOR, rtrim($docRoot, '/\\') . DIRECTORY_SEPARATOR);
    }
}
