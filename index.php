<?php
$uploadFolder = 'CHANGE THIS SECRET FOLDER NAME/';

// Increase the limit for file size to 14 MB
ini_set('upload_max_filesize', '14M');
ini_set('post_max_size', '14M');

$uploadedFiles = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $files = $_FILES['files'];

    // Check if the upload folder exists, create it if not
    if (!file_exists($uploadFolder)) {
        mkdir($uploadFolder, 0777, true);
    }

    $uploadErrors = [];

    // Loop through the files and perform the upload
    for ($i = 0; $i < count($files['name']); $i++) {
        $fileName = $files['name'][$i];
        $fileTmp = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        $fileError = $files['error'][$i];

        // Check if there are no errors during upload
        if ($fileError === 0) {
            $fileInfo = pathinfo($fileName);
            $fileExtension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'heic'];

            // Check if the file has an allowed image format
            if (in_array($fileExtension, $allowedExtensions)) {
                // Add a timestamp to the filename
                $timestamp = time();
                $newFileName = $timestamp . '_' . $fileName;
                $destination = $uploadFolder . $newFileName;

                // Attempt to move the file
                if (move_uploaded_file($fileTmp, $destination)) {
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

    // Output upload errors if any
    if (!empty($uploadErrors)) {
        foreach ($uploadErrors as $error) {
            echo $error . "<br>";
        }
    } elseif (!empty($uploadedFiles)) {
        echo "<h2>Upload Erfolgreich!</h2>";
        echo "<p>Folgende Bilder wurden erfolgreich hochgeladen:</p>";
        echo "<ul>";
        foreach ($uploadedFiles as $file) {
            echo "<li>$file</li>";
        }
        echo "</ul>";
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
    <h1>Bilder Upload</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="files">Bilder auswählen (max. 14 MB, jpg, jpeg, png, gif, heic):</label>
        <input type="file" name="files[]" id="files" accept="image/*" multiple>
        <br>
        <button type="submit">Hochladen</button>
    </form>
</body>
</html>
