# php-photo-inbox

A tiny PHP page that lets anybody upload photos into a private folder on your
web space. Uploading is public, looking at the uploaded photos over the web is
not.

## Installation

1. Copy the files (`index.php`, `lib/`) to your web space via FTP.
2. Open the page in the browser.
3. Done — that's the whole setup.

## How the photos are protected

`.htaccess` only exists for Apache. Nginx and Caddy ignore the file entirely,
so the protection cannot rely on it. Three layers, in this order:

1. **The storage folder is created outside the document root** whenever the
   filesystem allows it — typically one level above (`/home/user/` next to
   `/home/user/public_html/`). No web server serves what it cannot see, so this
   works on Apache, Nginx and Caddy without a single line of configuration.
   This is the normal case.
2. **If nothing outside the document root is writable**, the folder stays next
   to the app, but gets a random 96 bit name, every file an extra random token,
   and an empty `index.html` that stops directory listings.
3. **An `.htaccess` is written either way** (deny, no indexes, no PHP
   execution). Apache honours it, the other two ignore it.

Layer 2 is a fallback, not a guarantee — so the app **verifies it**: it writes a
canary file into the folder and fetches it over HTTP through the running web
server. If the file comes back, the page shows a warning with the exact rule to
paste into the server configuration. The check runs on the first start, once a
day, and on demand via `?recheck=1`.

### Choosing the location yourself

Set `PHOTO_INBOX_DIR` to an absolute path and the app stores the photos there,
no questions asked:

```apache
SetEnv PHOTO_INBOX_DIR /home/user/photos      # Apache
```
```nginx
fastcgi_param PHOTO_INBOX_DIR /home/user/photos;   # Nginx + PHP-FPM
```

### If the warning appears

The page prints the matching rule for your server, e.g.

```nginx
location ^~ /photo-inbox-abc123 {
    deny all;
    return 404;
}
```
```caddy
@photoInbox path /photo-inbox-abc123 /photo-inbox-abc123/*
respond @photoInbox 404
```

Reload the server, then follow the "Erneut prüfen" link — the warning
disappears once the folder is really unreachable.

## What else the first start does

* Creates the storage folder with the permissions `0755`, uploads with `0644`.
* Writes an `.htaccess` in the application root that keeps `config.php` from
  being served. No login — the upload form stays public on purpose.
* Stores the chosen path in `config.php` (mode `0600`).

Every following request re-checks folder, guards and permissions and repairs
what is missing, so a fresh FTP upload cannot silently drop the protection. If
the site is moved and the recorded path is gone, a new location is picked
automatically.

## Getting the photos

Via FTP, from the path recorded in `config.php`. The folder is deliberately not
reachable through the browser.

## Reset

Delete `config.php` and the generated `photo-inbox-*` folder — the next visit
sets everything up again.

## Requirements

* PHP 7.1 or newer
* Apache, Nginx or Caddy
