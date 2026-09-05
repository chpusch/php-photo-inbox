<?php
require __DIR__ . '/lib/setup.php';

// Already set up? Then there is nothing to do here.
if (isConfigured()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $repeat   = (string) ($_POST['password_repeat'] ?? '');

    if ($username === '' || !preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
        $errors[] = 'Bitte einen Benutzernamen mit 3-64 Zeichen (A-Z, a-z, 0-9, . _ -) angeben.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    }

    if ($password !== $repeat) {
        $errors[] = 'Die beiden Passwörter stimmen nicht überein.';
    }

    if (!$errors) {
        try {
            runSetup($username, $password);
            $done = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einrichtung</title>
</head>
<body>
<h1>Einrichtung</h1>

<?php if ($done): ?>
    <h2>Fertig!</h2>
    <p>Der geheime Upload-Ordner, die Berechtigungen, die .htaccess-Dateien und die .htpasswd wurden angelegt.</p>
    <p>Beim nächsten Aufruf fragt der Server nach Benutzername und Passwort.</p>
    <p><a href="index.php">Weiter zum Upload</a></p>
<?php else: ?>
    <p>Beim ersten Start werden alle Ordner, Rechte und Schutzdateien automatisch angelegt.
       Dafür wird nur ein Login für den Zugriff auf die Seite benötigt.</p>

    <?php if ($errors): ?>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form action="" method="post">
        <p>
            <label for="username">Benutzername</label><br>
            <input type="text" name="username" id="username" autocomplete="username"
                   value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </p>
        <p>
            <label for="password">Passwort (mind. 8 Zeichen)</label><br>
            <input type="password" name="password" id="password" autocomplete="new-password" required>
        </p>
        <p>
            <label for="password_repeat">Passwort wiederholen</label><br>
            <input type="password" name="password_repeat" id="password_repeat" autocomplete="new-password" required>
        </p>
        <button type="submit">Einrichten</button>
    </form>
<?php endif; ?>
</body>
</html>
