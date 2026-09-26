# Installation

ChurchCMS fresh install is designed for non-technical site operators.

## Standard flow

1. Upload/unpack ChurchCMS into the web directory.
2. Open `/install.php`.
3. Complete four short steps.
4. Sign in at `/admin/login`.

No Composer or shell access is required for the normal fresh-install path.

## Step 1 — automatic check

The installer checks PHP 8.3+, PDO, a supported DB driver, DOM, fileinfo, crypto/password functions, writable configuration/storage paths and the ChurchCMS core.

The operator sees plain green/red results rather than PHP diagnostics.

## Step 2 — site + database

The operator enters:

- site name;
- site profile;
- site URL (pre-filled automatically);
- PostgreSQL/MySQL;
- database name/user/password.

DB host/port are hidden under **Advanced settings**.

If the target database does not exist, ChurchCMS attempts to create it. If the DB account cannot create databases, the message tells the operator to create an empty DB in the hosting panel.

ChurchCMS then:

- prepares runtime storage;
- writes `config/local.php` atomically;
- runs all core/module migrations.

## Step 3 — first administrator

The installer creates the first `superadmin`.

Password is stored only through PHP's `password_hash(PASSWORD_DEFAULT)`.

## Step 4 — finish

The screen provides two obvious actions:

- enter the admin panel;
- open the public site.

The local configuration is marked `installation.completed = true`. Future requests to `install.php` return 404.

## Interrupted installation

Before the first administrator is created, `installation.completed` remains false. This intentionally allows the installation wizard to be reopened after a browser/session interruption.

## Safety

- isolated installer session;
- installer CSRF;
- HttpOnly + SameSite cookie;
- Secure cookie on HTTPS;
- deny framing;
- no stack traces;
- no credential echoing;
- strict DB name validation;
- prepared SQL for application records;
- atomic local config write;
- migrations are the only schema installation path;
- completed installer locks itself.

## Site profiles

The installer currently offers:

- Small parish;
- Parish / church;
- Cathedral;
- Theological school / seminary;
- Mixed church + education.

A later milestone will use the chosen profile to preselect the most useful modules and dashboard shortcuts.
