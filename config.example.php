<?php

declare(strict_types=1);

/**
 * Kopiere diese Datei nach `config.php` und passe die Werte an.
 * `config.php` gehoert NICHT ins Repository (siehe .gitignore).
 */

return [
    // Zielordner fuer die Uploads.
    //
    // Am sichersten liegt dieser Ordner AUSSERHALB des Document Root, z. B.:
    //     'upload_dir' => dirname(__DIR__) . '/photo-inbox-data',
    //
    // Liegt er innerhalb des Document Root, legt die Anwendung dort
    // automatisch eine .htaccess an, die den Web-Zugriff sperrt.
    'upload_dir' => __DIR__ . '/uploads',

    // Maximale Groesse pro Datei in Bytes.
    // Achtung: PHPs `upload_max_filesize` / `post_max_size` bleiben die harte
    // Obergrenze und lassen sich zur Laufzeit NICHT per ini_set() aendern.
    // Siehe README fuer die Einstellung via .htaccess bzw. php.ini.
    'max_file_bytes' => 14 * 1024 * 1024,

    // Maximale Anzahl Dateien pro Upload-Vorgang.
    'max_files_per_request' => 25,

    // Erlaubte Bildformate (MIME-Types). Der Typ wird anhand des Dateiinhalts
    // ermittelt, nicht anhand der Dateiendung.
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/avif',
    ],

    // Den (bereinigten) Originalnamen als Teil des Zieldateinamens behalten.
    'keep_original_name' => true,

    // Dateisystem-Rechte. 0750/0640 sind die sichere Wahl, wenn PHP und der
    // FTP-Zugang unter derselben Kennung laufen. Kommt der FTP-Client sonst
    // nicht an die Dateien, auf 0755/0644 erhoehen.
    'dir_permissions'  => 0750,
    'file_permissions' => 0640,

    // Ueberschrift der Seite.
    'app_name' => 'Foto-Postfach',
];
