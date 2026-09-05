# php-photo-inbox

A tiny PHP page that lets anybody upload photos into a private folder on your
web space. Uploading is public, looking at the uploaded photos over the web is
not.

## Installation

1. Copy the files (`index.php`, `lib/`) to your web space via FTP.
2. Open the page in the browser.
3. Done — that's the whole setup.

## What the first start does automatically

* It picks a random, hard to guess name for the upload folder
  (`inbox-<random>`) and creates it with the permissions `0755`.
* It writes an `.htaccess` into that folder that denies web access completely
  and switches PHP execution off there, so the photos cannot be browsed,
  guessed or executed.
* It writes an `.htaccess` in the application root that keeps `config.php` from
  being served. No login — the upload form stays public.
* It stores the chosen folder name in `config.php` (mode `0600`).

Every following request re-checks the folder, the `.htaccess` files and the
permissions and repairs them if something is missing, so a fresh FTP upload
cannot silently drop the protection.

You get the uploaded photos off the server via FTP; the upload folder name is
in `config.php`.

## Reset

Delete `config.php` and the generated `inbox-*` folder — the next visit sets
everything up again.

## Requirements

* PHP 7.1 or newer
* Apache with `.htaccess` support (`AllowOverride`) enabled

On Nginx, `.htaccess` files are ignored: keep the upload folder out of the
served locations in the server config instead.
