<?php
/**
 * Bootstrap / self-setup helpers.
 *
 * Everything the README used to ask for manually (secret upload folder,
 * permissions, .htaccess protection) is created on the first start and
 * re-checked (idempotently) on every following request.
 *
 * The page itself stays public - anybody may upload. What is protected is the
 * upload folder: it is not readable over the web at all.
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

/** The root .htaccess: no login, it only keeps the configuration private. */
function rootHtaccess(): string
{
    return <<<'HTACCESS'
# Generated automatically - do not edit, it will be rewritten on the next start.
# The upload page is public on purpose, only the configuration is off limits.
<FilesMatch "^config\.php$">
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

/**
 * Creates/repairs every folder and file the app needs.
 *
 * Safe to call on every request: existing files are left alone unless their
 * content drifted, and permissions are re-applied.
 *
 * @param array<string,mixed> $config
 * @return string absolute path of the upload folder
 */
function ensureEnvironment(array $config): string
{
    $root      = dirname(__DIR__);
    $uploadDir = $root . '/' . $config['upload_folder'];

    ensureDirectory($uploadDir);
    ensureFile($uploadDir . '/.htaccess', denyAllHtaccess() . "\n");
    ensureFile($root . '/.htaccess', rootHtaccess());

    return $uploadDir;
}

/**
 * First start: picks the secret folder name, creates the directory incl. its
 * .htaccess and stores the configuration. No user interaction needed.
 *
 * @return array<string,mixed> the freshly written configuration
 */
function runSetup(): array
{
    $config = [
        'upload_folder' => randomFolderName('inbox'),
        'created_at'    => date('c'),
    ];

    ensureEnvironment($config);
    saveConfig($config);

    return $config;
}
