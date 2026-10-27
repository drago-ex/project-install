# Drago Project install

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

## Files copied into the project

The files are copied into the project by [drago-ex/project-tools](https://github.com/drago-ex/project-tools),
as set in `extra.drago-tools` of this package's `composer.json`.

## How it works

Until the application is installed, `www/index.php` runs only the installer (`app/Presentation/Install`).
The wizard tests the database connection, writes `app/db.neon`, runs the migrations, sets up the website
and creates the administrator account. The steps go in order and cannot be skipped.

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

## Extending the installer

Another package can add its own steps by a service in `app/Presentation/Install/` (the installer loads its neon
files and classes):

- tag `install.migrations`: a callable object that gets the project root and returns more migrations; they run after
  the package migrations and before the project's own,
- tag `install.account`: a callable object that gets the id of the new administrator.

```neon
services:
	- {factory: App\Presentation\Install\AdminRole, tags: [install.account], autowired: no}
```

[drago-ex/project-preset-permission](https://github.com/drago-ex/project-preset-permission) adds the privileges and the
`admin` role this way.
