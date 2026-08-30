# Foto-Postfach

Ein kleines, eigenständiges PHP-Upload-Formular: Gäste laden Bilder hoch, die
Dateien landen in einem Ordner, den nur du per FTP siehst. Keine Datenbank,
keine Abhängigkeiten, kein Composer – hochladen und läuft.

![Formular](https://img.shields.io/badge/PHP-8.1%2B-777bb4) ![Abhängigkeiten](https://img.shields.io/badge/Abh%C3%A4ngigkeiten-keine-brightgreen)

## Funktionsumfang

* Drag & Drop, Mehrfachauswahl, Vorschaubilder und Fortschrittsanzeige
* Läuft auch **ohne JavaScript** als klassisches Formular
* Helles und dunkles Design, folgt der Systemeinstellung
* JPG, PNG, GIF, WEBP, HEIC/HEIF und AVIF
* Formaterkennung **anhand des Dateiinhalts**, nicht anhand der Endung

## Installation

### 1. Dateien hochladen

Den kompletten Inhalt dieses Ordners per FTP in das gewünschte Verzeichnis
legen.

### 2. Konfiguration anlegen

`config.example.php` nach `config.php` kopieren und anpassen:

```php
'upload_dir' => dirname(__DIR__) . '/foto-postfach-daten',
'max_file_bytes' => 14 * 1024 * 1024,
```

Am sichersten liegt der Upload-Ordner **außerhalb des Document Root** – dann
ist er über das Web grundsätzlich nicht erreichbar. Ist das beim Hoster nicht
möglich, lege ihn innerhalb ab: die Anwendung erzeugt dort automatisch eine
`.htaccess`, die den Web-Zugriff sperrt und die Ausführung von Skripten
unterbindet. Der Ordner wird beim ersten Upload selbst angelegt.

### 3. Zugangsschutz einrichten

Ohne diesen Schritt läuft die Seite bewusst **nicht** – ein offenes
Upload-Formular im Netz ist eine Einladung.

Passwortdatei auf dem eigenen Rechner erzeugen (macOS/Linux-Terminal):

```bash
htpasswd -c -B .htpasswd BENUTZERNAME
```

Die Datei per FTP an einen Ort **außerhalb des Document Root** legen und den
absoluten Pfad in der `.htaccess` eintragen:

```apache
AuthUserFile /absoluter/pfad/ausserhalb/des/webroots/.htpasswd
```

Den Pfad findest du üblicherweise im Hosting-Panel. Die `.htpasswd` gehört
niemals ins Repository und niemals in einen öffentlichen Ordner.

### 4. Upload-Limits setzen

`upload_max_filesize` und `post_max_size` lassen sich zur Laufzeit **nicht**
per `ini_set()` ändern – das muss auf Serverebene passieren. Beides ist
vorbereitet:

* **`.htaccess`** – greift bei Hostern mit `mod_php`
* **`.user.ini`** – greift bei Hostern mit PHP-FPM/CGI (die Mehrheit)

Beide stehen auf 14 MB pro Datei. Wenn du das änderst, `post_max_size` etwas
höher als `upload_max_filesize` lassen und `max_file_bytes` in der
`config.php` mitziehen. Die Seite zeigt immer das *tatsächlich* wirksame
Limit an – ist die Anzeige niedriger als erwartet, hat der Hoster das letzte
Wort und die Werte müssen im Panel gesetzt werden.

## Wie die Uploads geprüft werden

Jede Datei durchläuft serverseitig dieselbe Kette – die Prüfung im Browser ist
reiner Komfort und wird nicht als Sicherheitsmerkmal behandelt:

| Prüfung | Wogegen sie schützt |
|---|---|
| CSRF-Token pro Sitzung | Fremde Seiten laden in deinem Namen hoch |
| `is_uploaded_file()` | Untergeschobene Serverpfade |
| Größe gegen das wirksame Limit | Volllaufende Festplatte |
| Formaterkennung über `finfo` + Container-Signatur | `shell.php` mit `.jpg` im Namen |
| Gegenprobe mit `getimagesize()` | Dateien, die nur wie ein Bild beginnen |
| Serverseitig gebildeter Dateiname | Verzeichniswechsel (`../`), Doppelendungen (`.php.jpg`) |
| `.htaccess` im Zielordner | Ausführung dessen, was doch durchkommt |
| Ausgabe komplett HTML-escaped | XSS über präparierte Dateinamen |

Der Zieldateiname wird vollständig vom Server gebildet:

```
2026-08-30_142530_a3f9c1d0_strandfoto.jpg
└ Zeitstempel ┘ └ Zufall ┘ └ Originalname ┘ └ Endung aus dem Inhalt ┘
```

Vom hochgeladenen Namen überlebt nur ein auf ASCII reduzierter, gekürzter
Rest. Die Endung stammt immer aus dem erkannten Format, nie aus der Eingabe.

## Aufbau

```
index.php              Front-Controller: Konfiguration, Session, Routing
config.example.php     Vorlage für die eigene config.php
src/
  bootstrap.php        Autoloader + Escaping-Helfer
  Config.php           Getypte, validierte Konfiguration
  Csrf.php             Token erzeugen und prüfen
  Flash.php            Ergebnis über den Redirect tragen
  FormatDetector.php   Bildformat aus dem Dateiinhalt
  Http.php             Session-Härtung, Sicherheits-Header, Redirect, JSON
  ImageFormat.php      Unterstützte Formate als Enum
  PhpLimits.php        ini-Größen, Limit-Erkennung, Formatierung
  Uploader.php         Die eigentliche Prüf- und Speicherkette
  UploadCandidate.php  Eine Datei aus $_FILES, noch ungeprüft
  UploadDirectory.php  Zielordner anlegen und abschotten
  SavedFile.php        Ergebnis: gespeichert
  RejectedFile.php     Ergebnis: abgelehnt
  UploadReport.php     Gesamtergebnis eines Vorgangs
templates/upload.php   Die Seite
assets/style.css       Gestaltung (hell/dunkel)
assets/app.js          Drag & Drop, Vorschau, Fortschritt
```

## Voraussetzungen

PHP 8.1 oder neuer mit den Erweiterungen `fileinfo` und `mbstring` – beide
sind in Standardinstallationen enthalten. Apache mit `.htaccess`-Unterstützung
für den Zugangsschutz.
