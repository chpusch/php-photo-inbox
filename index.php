<?php
require __DIR__ . '/lib/setup.php';

// First start: send the visitor to the setup wizard, which creates the
// folders, permissions, .htaccess and .htpasswd automatically.
if (!isConfigured()) {
    header('Location: setup.php');
    exit;
}

$config = loadConfig();

$setupErrors = [];

try {
    // Re-creates anything that is missing (e.g. after a fresh FTP upload).
    $paths        = ensureEnvironment($config);
    $uploadFolder = $paths['upload'] . '/';
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
                // Add a timestamp to the filename
                $safeName    = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
                $newFileName = time() . '_' . $safeName;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilder Upload Erfolgreich</title>
</head>
<body>
    <?php foreach ($uploadErrors as $error): ?>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?><br>
    <?php endforeach; ?>

    <?php if (!$uploadErrors && $uploadedFiles): ?>
        <h2>Upload Erfolgreich!</h2>
        <p>Folgende Bilder wurden erfolgreich hochgeladen:</p>
        <ul>
            <?php foreach ($uploadedFiles as $file): ?>
                <li><?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></li>
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
