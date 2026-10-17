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

`www/index.php` boots one of two DI containers:

- **Install mode** (`bootInstallApplication()`): only the installer in `app/Presentation/Install` and
  `app/db.neon`. The wizard tests the database connection, writes `app/db.neon`, runs the SQL files from
  `migrations/`, sets up the website and creates the administrator account.
- **Application mode** (`bootWebApplication()`): the whole application without the installer.

The wizard steps follow each other (database, migrations, website, administrator account, finish). Forms
and signals of a step that is not current answer with HTTP 403, so the steps cannot be skipped or repeated
by sending requests directly. The administrator role is assigned to the account created in the wizard.

The console (`bootConsoleApplication()`) and `nette/mcp-inspector` (`bootConfigurator()`) use the same
configuration as the application, including the RobotLoader that excludes the installer.

## Installation lock

Finishing the wizard stores `installed = 1` in the `settings` table and writes `app/installed.lock`.
While the lock exists, the application mode is used. The lock is kept in `app/`, next to `db.neon`,
so clearing `temp/` does not reopen the installer. A lock in `temp/` written by older versions is
still accepted and moved to `app/`.

When the lock is missing, the installer checks the database and recreates the lock if the application
is already installed. Keep `app/installed.lock` on the server, and decide whether to commit it or add it
to `.gitignore` depending on how you deploy.

## Migrations

The project's `migrations/` directory is the single source of migrations. Copy the SQL files of installed
packages into it with `vendor/bin/sql-export`.

The installer runs these files through `drago-ex/migration`, so they are recorded in the `migrations` table
exactly as with the console:

- an executed file is recorded with its checksum and skipped next time, so the installer can be run again
  after a failure;
- a recorded file that was changed is refused;
- later migrations, for example after a package update, are added to `migrations/` with `sql-export` and
  run from the console:

```bash
php vendor/bin/sql-export
php vendor/bin/migration db:migrate migrations
```

The console skips everything the installer has already run. Do not run package migrations from `vendor/`
(for example the `db:migrate-*` commands of `drago-setup`) on a database set up by the installer: files
run from `vendor/` are recorded under the package name, so they would be executed again.

## Console

`app/app.neon` registers `drago-ex/migration` and `contributte/console`, so commands such as
`vendor/bin/migration` work. Inside Docker, run them as the web user, so files in `temp/` keep the
right owner:

```bash
docker compose exec -u www-data server php vendor/bin/migration list
```
