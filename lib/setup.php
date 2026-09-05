<?php
/**
 * Bootstrap / self-setup helpers.
 *
 * Everything the README used to ask for manually (secret upload folder,
 * permissions, .htaccess protection, .htpasswd) is created from here on the
 * first start and re-checked (idempotently) on every following request.
 */

const CONFIG_FILE = __DIR__ . '/../config.php';

const DIR_MODE  = 0755;
const FILE_MODE = 0644;

/** Content for an .htaccess that blocks direct web access to a folder. */
function denyAllHtaccess(): string
{
    return <<<'HTACCESS'
# Generated automatically - do not edit, it will be rewritten.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>

# Never execute anything that ends up in here.
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>
HTACCESS;
}

function isConfigured(): bool
{
    return is_file(CONFIG_FILE);
}

/** @return array<string,mixed> */
function loadConfig(): array
{
    if (!isConfigured()) {
        return [];
    }

    $config = include CONFIG_FILE;

    return is_array($config) ? $config : [];
}

/** @param array<string,mixed> $config */
function saveConfig(array $config): void
{
    $export = var_export($config, true);
    $php    = "<?php\n// Generated automatically on first start. Keep this file private.\nreturn {$export};\n";

    if (file_put_contents(CONFIG_FILE, $php, LOCK_EX) === false) {
        throw new RuntimeException('Could not write config.php. Make the application directory writable for the web server.');
    }

    @chmod(CONFIG_FILE, 0600);
}

/** A hard to guess, filesystem friendly folder name. */
function randomFolderName(string $prefix): string
{
    return $prefix . '-' . bin2hex(random_bytes(12));
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, DIR_MODE, true) && !is_dir($path)) {
        throw new RuntimeException("Could not create the directory '{$path}'. Check the write permissions of the application directory.");
    }

    @chmod($path, DIR_MODE);
}

/** Writes a file only when its content differs, so permissions stay stable. */
function ensureFile(string $path, string $content, int $mode = FILE_MODE): void
{
    if (!is_file($path) || file_get_contents($path) !== $content) {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException("Could not write '{$path}'. Check the write permissions of the application directory.");
        }
    }

    @chmod($path, $mode);
}

function ensureProtectedDirectory(string $path): void
{
    ensureDirectory($path);
    ensureFile($path . '/.htaccess', denyAllHtaccess() . "\n");
}

/** The .htaccess that puts the whole app behind basic auth. */
function rootHtaccess(string $htpasswdPath, string $realm): string
{
    $htpasswdPath = str_replace('"', '\"', $htpasswdPath);
    $realm        = str_replace('"', '\"', $realm);

    return <<<HTACCESS
# Generated automatically - do not edit, it will be rewritten on the next start.
AuthType Basic
AuthName "{$realm}"
AuthUserFile "{$htpasswdPath}"
Require valid-user

# The generated configuration and the password file are never served.
<FilesMatch "^(config\.php|\.htpasswd)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

HTACCESS;
}

/**
 * Creates/repairs every folder and file the app needs.
 *
 * Safe to call on every request: existing files are left alone unless their
 * content drifted, and permissions are re-applied.
 *
 * @param array<string,mixed> $config
 * @return array<string,string> absolute paths of the prepared locations
 */
function ensureEnvironment(array $config): array
{
    $root = dirname(__DIR__);

    $privateDir = $root . '/' . $config['private_folder'];
    $uploadDir  = $root . '/' . $config['upload_folder'];

    ensureProtectedDirectory($privateDir);
    ensureProtectedDirectory($uploadDir);

    $htpasswd = $privateDir . '/.htpasswd';

    if (is_file($htpasswd)) {
        @chmod($htpasswd, 0640);
        ensureFile($root . '/.htaccess', rootHtaccess($htpasswd, $config['auth_realm'] ?? 'Bilder Upload'));
    }

    return [
        'private' => $privateDir,
        'upload'  => $uploadDir,
        'htpasswd' => $htpasswd,
    ];
}

/**
 * Runs the first-start setup: picks the secret folder names, creates the
 * directories incl. their .htaccess files and stores the credentials.
 *
 * @return array<string,mixed> the freshly written configuration
 */
function runSetup(string $username, string $password): array
{
    $config = [
        'upload_folder'  => randomFolderName('inbox'),
        'private_folder' => randomFolderName('.private'),
        'auth_user'      => $username,
        'auth_realm'     => 'Bilder Upload',
        'created_at'     => date('c'),
    ];

    $paths = ensureEnvironment($config);

    $hash = password_hash($password, PASSWORD_BCRYPT);
    ensureFile($paths['htpasswd'], $username . ':' . $hash . "\n", 0640);

    ensureFile(dirname(__DIR__) . '/.htaccess', rootHtaccess($paths['htpasswd'], $config['auth_realm']));

    saveConfig($config);

    return $config;
}
