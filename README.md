# php-photo-inbox

A tiny PHP page that lets people upload photos into a private folder on your
web space.

## Installation

1. Copy the files (`index.php`, `setup.php`, `lib/`) to your web space via FTP.
2. Open the page in the browser. On the first start you are sent to the setup
   wizard, where you only choose a username and a password.
3. Done.

Do step 2 right after the upload: until the setup has run, the page is not yet
password protected.

## What the first start does automatically

* It picks a random, hard to guess name for the upload folder
  (`inbox-<random>`) and creates it with the permissions `0755`.
* It creates a private folder (`.private-<random>`) for the password file.
* It writes an `.htaccess` into both folders that denies direct web access and
  switches PHP execution off there.
* It creates the `.htpasswd` (bcrypt hash, so Apache 2.4+) inside the private
  folder — no more `htpasswd` command in the terminal.
* It writes the `.htaccess` in the application root that puts the page behind
  basic auth, with the absolute path to the generated `.htpasswd`, and blocks
  `config.php` and `.htpasswd` from being served.
* It stores the chosen names in `config.php` (mode `0600`).

Every following request re-checks the folders, `.htaccess` files and
permissions and repairs them if something is missing, so a fresh FTP upload
cannot break the protection.

## Reset

Delete `config.php` and the generated folders — the next visit starts the setup
wizard again.

## Requirements

* PHP 7.1 or newer
* Apache with `.htaccess` support (`AllowOverride`) enabled

On Nginx, `.htaccess` files are ignored: keep the upload and private folders out
of the served locations and configure basic auth in the server config instead.
