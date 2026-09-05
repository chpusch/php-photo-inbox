<?php
/**
 * Bootstrap / self-setup helpers.
 *
 * The uploaded photos must be unreachable over the web on Apache, Nginx and
 * Caddy alike. Only Apache reads .htaccess, so the protection cannot rely on
 * it. Three layers, in this order:
 *
 *   1. The storage folder is created OUTSIDE the document root whenever the
 *      filesystem allows it. No web server serves what it cannot see, so this
 *      needs no configuration on any of the three.
 *   2. If nothing outside the document root is writable, the folder stays next
 *      to the app but gets a random 96 bit name and every file an extra random
 *      token, and directory listings are switched off.
 *   3. An .htaccess (deny + no indexes + no PHP) is written either way. It is
 *      simply ignored by Nginx and Caddy.
 *
 * Layer 2 is a fallback, not a guarantee - so the app verifies over HTTP
 * whether the folder is really unreachable and says so on the page.
 */

const CONFIG_FILE = __DIR__ . '/../config.php';

const DIR_MODE  = 0755;
const FILE_MODE = 0644;

/** Canary file used by the HTTP self check. */
const CANARY_FILE = 'protection-check.txt';

const PROTECTION_OUTSIDE_WEBROOT = 'outside-webroot';
const PROTECTION_BLOCKED         = 'blocked';
const PROTECTION_EXPOSED         = 'exposed';
const PROTECTION_UNKNOWN         = 'unknown';

function appRoot(): string
{
    return dirname(__DIR__);
}

function normalizePath(string $path): string
{
    $real = realpath($path);

    return rtrim(str_replace('\\', '/', $real !== false ? $real : $path), '/');
}

/** The document root as the web server reports it, if it reports one. */
function documentRoot(): ?string
{
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

    if (!is_string($docRoot) || $docRoot === '' || !is_dir($docRoot)) {
        return null;
    }

    return normalizePath($docRoot);
}

function pathIsInside(string $path, string $parent): bool
{
    $path   = normalizePath($path);
    $parent = normalizePath($parent);

    return $path === $parent || strpos($path . '/', $parent . '/') === 0;
}

/**
 * Would a file in this directory be served by the web server?
 *
 * Anything under the document root is servable by default on Apache, Nginx and
 * Caddy. With no document root reported (CLI, some FPM setups) we assume the
 * worst and treat the app directory as servable.
 */
function isServable(string $path): bool
{
    $docRoot = documentRoot();

    if ($docRoot === null) {
        return pathIsInside($path, appRoot());
    }

    return pathIsInside($path, $docRoot);
}

function isUsableParent(?string $path): bool
{
    return $path !== null && $path !== '' && is_dir($path) && is_writable($path);
}

/**
 * Directories the storage folder could live in, best first.
 *
 * @return list<string>
 */
function storageParentCandidates(): array
{
    $candidates = [];

    $docRoot = documentRoot();
    $appRoot = appRoot();

    // One level above the document root: the classic shared hosting layout
    // (/home/user/ above /home/user/public_html/).
    if ($docRoot !== null) {
        $candidates[] = dirname($docRoot);
    }

    // The app may sit in a subdirectory of the document root - then its own
    // parent chain up to the document root is still inside the web root, so
    // only the level above the document root helps. Try the app's parent
    // anyway for setups that report no document root.
    $candidates[] = dirname($appRoot);

    // Last resort: next to the app itself, i.e. inside the web root.
    $candidates[] = $appRoot;

    $unique = [];
    foreach ($candidates as $candidate) {
        $candidate = normalizePath($candidate);
        if ($candidate !== '' && !in_array($candidate, $unique, true)) {
            $unique[] = $candidate;
        }
    }

    return $unique;
}

/**
 * An explicitly configured storage location, e.g. via Apache SetEnv or
 * fastcgi_param PHOTO_INBOX_DIR. It always wins over the automatic choice -
 * the admin knows the server better than the guesswork below.
 */
function configuredStorageParent(): ?string
{
    $configured = getenv('PHOTO_INBOX_DIR');

    if (!is_string($configured) || $configured === '') {
        return null;
    }

    $configured = normalizePath(rtrim($configured, '/'));

    return isUsableParent($configured) ? $configured : null;
}

/** Picks the configured location, else the first writable non-servable one. */
function chooseStorageParent(): string
{
    $configured = configuredStorageParent();

    if ($configured !== null) {
        return $configured;
    }

    $candidates = storageParentCandidates();

    foreach ($candidates as $candidate) {
        if (isUsableParent($candidate) && !isServable($candidate)) {
            return $candidate;
        }
    }

    foreach ($candidates as $candidate) {
        if (isUsableParent($candidate)) {
            return $candidate;
        }
    }

    return normalizePath(appRoot());
}

/** Content for an .htaccess that blocks web access - Apache only, by design. */
function denyAllHtaccess(): string
{
    return <<<'HTACCESS'
# Generated automatically - do not edit, it will be rewritten.
# Apache only. Nginx and Caddy ignore this file, which is why the folder is
# placed outside the document root whenever that is possible.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>

Options -Indexes
IndexIgnore *

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

/** A hard to guess, filesystem friendly folder name (96 bit). */
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
 * Creates/repairs the storage folder and its guards.
 *
 * Safe to call on every request. If the recorded parent directory disappeared
 * (the site was moved), a new location is picked and the config is updated.
 *
 * @param array<string,mixed> $config passed by reference, may be updated
 * @return string absolute path of the storage folder
 */
function ensureEnvironment(array &$config): string
{
    $parent = $config['storage_parent'] ?? '';

    if (!isUsableParent($parent)) {
        $parent                   = chooseStorageParent();
        $config['storage_parent'] = $parent;
        $config['servable']       = isServable($parent);
        saveConfig($config);
    }

    $storageDir = $parent . '/' . $config['storage_folder'];

    ensureDirectory($storageDir);
    ensureFile($storageDir . '/.htaccess', denyAllHtaccess() . "\n");

    // Kills directory listings on servers that have them switched on.
    ensureFile($storageDir . '/index.html', "");

    ensureFile(appRoot() . '/.htaccess', rootHtaccess());

    return $storageDir;
}

/**
 * First start: picks the location and the secret folder name, creates the
 * directory incl. its guards and stores the configuration. No user
 * interaction needed.
 *
 * @return array<string,mixed> the freshly written configuration
 */
function runSetup(): array
{
    $parent = chooseStorageParent();

    $config = [
        'storage_parent' => $parent,
        'storage_folder' => randomFolderName('photo-inbox'),
        'servable'       => isServable($parent),
        'created_at'     => date('c'),
        'protection'     => ['status' => PROTECTION_UNKNOWN],
    ];

    ensureEnvironment($config);
    saveConfig($config);

    return $config;
}

/**
 * The URL a visitor would use to reach the canary file, or null when the
 * folder is not below the app's URL path at all.
 */
function canaryUrl(array $config): ?string
{
    // The host as the visitor sees it - it carries the port a reverse proxy
    // listens on, which SERVER_NAME does not.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!is_string($host) || $host === '') {
        $host = $_SERVER['SERVER_NAME'] ?? '';
    }

    // Only ever request our own host, never something a header talked us into.
    if (!is_string($host) || !preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        return null;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!is_string($script) || $script === '') {
        return null;
    }

    $base   = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    return $scheme . '://' . $host . $base . '/' . rawurlencode($config['storage_folder']) . '/' . CANARY_FILE;
}

/** @return array{0:?int,1:string} status code and body */
function fetchUrl(string $url): array
{
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            // We are talking to our own host: never take a detour over a
            // proxy that http_proxy in the environment would impose.
            CURLOPT_PROXY          => '',
        ]);

        $body   = (string) curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return [$status ?: null, $body];
    }

    if (!ini_get('allow_url_fopen')) {
        return [null, ''];
    }

    $context = stream_context_create([
        // 'proxy' stays unset on purpose, see the curl branch above.
        'http' => ['timeout' => 5, 'follow_location' => 0, 'ignore_errors' => true],
        // The canary carries no secret, a self signed certificate is fine.
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $body   = @file_get_contents($url, false, $context);
    $status = null;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
            $status = (int) $match[1];
        }
    }

    return [$status, is_string($body) ? $body : ''];
}

/**
 * Asks the web server itself whether the storage folder is reachable.
 *
 * A folder outside the document root is protected by construction and is not
 * probed at all. Inside the web root a canary file is written and fetched over
 * HTTP: if it comes back, the server ignores the .htaccess and the owner has
 * to add a rule - the page then says exactly that.
 *
 * @param array<string,mixed> $config passed by reference, result is stored
 * @return array{status:string,url:?string,checked_at:string}
 */
function verifyProtection(array &$config, string $storageDir): array
{
    if (!isServable($storageDir)) {
        $result = [
            'status'     => PROTECTION_OUTSIDE_WEBROOT,
            'url'        => null,
            'checked_at' => date('c'),
        ];

        $config['protection'] = $result;
        saveConfig($config);

        return $result;
    }

    $url    = canaryUrl($config);
    $status = PROTECTION_UNKNOWN;

    if ($url !== null) {
        $token = bin2hex(random_bytes(16));
        ensureFile($storageDir . '/' . CANARY_FILE, $token);

        [$code, $body] = fetchUrl($url);

        if ($code === null) {
            // No answer at all - the check itself failed, say nothing certain.
            $status = PROTECTION_UNKNOWN;
        } elseif ($code === 200 && trim($body) === $token) {
            // The exact canary content came back: the folder is served.
            $status = PROTECTION_EXPOSED;
        } else {
            // A denial, a 404 or somebody else's page (a rewrite fallback):
            // either way the file itself is not retrievable.
            $status = PROTECTION_BLOCKED;
        }
    }

    $result = [
        'status'     => $status,
        'url'        => $url,
        'checked_at' => date('c'),
    ];

    $config['protection'] = $result;
    saveConfig($config);

    return $result;
}

/** @param array<string,mixed> $config */
function protectionStatus(array $config): string
{
    return $config['protection']['status'] ?? PROTECTION_UNKNOWN;
}

/** Ready to paste rules for the servers that do not read .htaccess. */
function serverRuleSnippets(string $folder): array
{
    return [
        // Both rules cover the folder itself as well as everything below it.
        'Nginx' => "location ^~ /{$folder} {\n    deny all;\n    return 404;\n}",
        'Caddy' => "@photoInbox path /{$folder} /{$folder}/*\nrespond @photoInbox 404",
    ];
}
