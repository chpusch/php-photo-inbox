<?php
require __DIR__ . '/lib/setup.php';

$setupErrors = [];
$protection  = PROTECTION_UNKNOWN;
$config      = [];

try {
    // First start: create the storage folder, its permissions and its guards.
    // Later starts only re-check that everything is still in place.
    $config = isConfigured() ? loadConfig() : runSetup();

    $uploadFolder = ensureEnvironment($config) . '/';

    // Verify with the running web server that the folder really is unreachable
    // - .htaccess means nothing on Nginx and Caddy. Re-checked daily and on
    // demand via ?recheck=1, so a changed server config does not go unnoticed.
    $lastCheck = strtotime($config['protection']['checked_at'] ?? '') ?: 0;
    $recheck   = isset($_GET['recheck']) || (time() - $lastCheck) > 86400;

    $protection = $recheck
        ? verifyProtection($config, rtrim($uploadFolder, '/'))['status']
        : protectionStatus($config);
} catch (Throwable $e) {
    $setupErrors[] = $e->getMessage();
    $uploadFolder  = null;
}

// Increase the limit for file size to 14 MB
ini_set('upload_max_filesize', '14M');
ini_set('post_max_size', '14M');

$uploadedFiles = [];
$uploadErrors  = $setupErrors;

if ($uploadFolder !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $files = $_FILES['files'];

    // Loop through the files and perform the upload
    for ($i = 0; $i < count($files['name']); $i++) {
        $fileName  = basename((string) $files['name'][$i]);
        $fileTmp   = $files['tmp_name'][$i];
        $fileError = $files['error'][$i];

        // Check if there are no errors during upload
        if ($fileError === 0) {
            $fileInfo          = pathinfo($fileName);
            $fileExtension     = strtolower($fileInfo['extension'] ?? '');
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'heic'];

            // Check if the file has an allowed image format
            if (in_array($fileExtension, $allowedExtensions, true)) {
                // Timestamp for sorting plus a random token, so a single file
                // stays unguessable even if the folder name ever leaks.
                $safeName    = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
                $newFileName = date('Ymd-His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
                $destination = $uploadFolder . $newFileName;

                // Attempt to move the file
                if (move_uploaded_file($fileTmp, $destination)) {
                    @chmod($destination, 0644);
                    $uploadedFiles[] = $newFileName;
                } else {
                    $uploadErrors[] = "An error occurred while uploading the file $fileName.";
                }
            } else {
                $uploadErrors[] = "Invalid file format for $fileName. Allowed formats: " . implode(', ', $allowedExtensions);
            }
        } else {
            $uploadErrors[] = "An error occurred while uploading the file $fileName.";
        }
    }
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilder Upload</title>
</head>
<body>
    <?php if ($protection === PROTECTION_EXPOSED || $protection === PROTECTION_UNKNOWN): ?>
        <?php $folder = $config['storage_folder'] ?? ''; ?>
        <div style="border:2px solid #b00; padding:1em; margin-bottom:1em;">
            <?php if ($protection === PROTECTION_EXPOSED): ?>
                <strong>Achtung: Der Upload-Ordner ist über das Web erreichbar.</strong>
                <p>Der Server liest keine <code>.htaccess</code> (typisch für Nginx und Caddy).
                   Bitte eine der folgenden Regeln in die Server-Konfiguration eintragen —
                   oder den Ordner per <code>PHOTO_INBOX_DIR</code> außerhalb des Web-Roots ablegen.</p>
            <?php else: ?>
                <strong>Der Schutz des Upload-Ordners konnte nicht geprüft werden.</strong>
                <p>Der Selbsttest per HTTP war nicht möglich. Bitte einmal von Hand prüfen, ob
                   <code><?= $escape($config['protection']['url'] ?? '') ?></code> erreichbar ist.
                   Falls ja, hilft eine dieser Regeln:</p>
            <?php endif; ?>
            <?php foreach (serverRuleSnippets($folder) as $server => $snippet): ?>
                <p><strong><?= $escape($server) ?></strong></p>
                <pre><?= $escape($snippet) ?></pre>
            <?php endforeach; ?>
            <p><a href="?recheck=1">Erneut prüfen</a></p>
        </div>
    <?php endif; ?>

    <?php foreach ($uploadErrors as $error): ?>
        <?= $escape($error) ?><br>
    <?php endforeach; ?>

    <?php if (!$uploadErrors && $uploadedFiles): ?>
        <h2>Upload Erfolgreich!</h2>
        <p>Folgende Bilder wurden erfolgreich hochgeladen:</p>
        <ul>
            <?php foreach ($uploadedFiles as $file): ?>
                <li><?= $escape($file) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h1>Bilder Upload</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="files">Bilder auswählen (max. 14 MB, jpg, jpeg, png, gif, heic):</label>
        <input type="file" name="files[]" id="files" accept="image/*" multiple>
        <br>
        <button type="submit">Hochladen</button>
    </form>
</body>
</html>
