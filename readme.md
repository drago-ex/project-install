# Drago Project install

English | [Čeština](readme.cs.md)

Application installation package.

[![PHP version](https://badge.fury.io/ph/drago-ex%2Fproject-install.svg)](https://badge.fury.io/ph/drago-ex%2Fproject-install)
[![Coding Style](https://github.com/drago-ex/project-install/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/project-install/actions/workflows/coding-style.yml)

## Requirements

- PHP >= 8.3, < 9
- Nette Framework
- Composer
- Bootstrap
- Naja
- Node.js
- Drago Project core packages

## Installation

```bash
composer require drago-ex/project-install
```

## Project files

File copying is handled automatically by [drago-ex/project-tools](https://github.com/drago-ex/project-tools),
which must be installed in your project. Without it, copy the files manually according to the `copy` section
in this package's `composer.json`. To skip this package, set `"skip": true` under
`extra.drago-tools.packages.<package-name>` in your root `composer.json`.

> Warning: This package uses the `replace` section, which means some files will be **overwritten if they already exist**.
> Avoid manual edits to those files; use the `skip` option if you need to manage them yourself.

The replaced files (`app/Bootstrap.php`, `app/app.neon`, `www/`) are copied only once (`replace-once`).
Later versions of this package do not update them in an existing project, so apply changes from new
versions manually.

## How it works

Until the application is installed, `www/index.php` runs only the installer (`app/Presentation/Install`).
The wizard tests the database connection, writes `app/db.neon`, runs the migrations, sets up the website
and creates the administrator account. The steps cannot be skipped or repeated.

## Installation lock

Finishing the wizard writes `app/installed.lock`; while it exists, the application runs instead of the installer.
Keep it on the server, and commit it or add it to `.gitignore` depending on how you deploy.

## Migrations

The installer runs the migrations of all installed packages in their `migration-priority` order, then the project's
own `migrations/`, the same way as `vendor/bin/migration db:migrate --packages`. Executed files are recorded,
so the installer can be run again after a failure, and later migrations run from the console:

```bash
php vendor/bin/migration db:migrate --packages
```

## Console

`app/app.neon` registers `drago-ex/migration` and `contributte/console`, so commands such as
`vendor/bin/migration` work. Inside Docker, run them as the web user, so files in `temp/` keep the
right owner:

```bash
docker compose exec -u www-data server php vendor/bin/migration list
```
