# Agent Guide

Use this file as package-specific guidance when working in this repository. Keep changes aligned with the existing public API, project conventions, and supported PHP range.

## Package Overview
- **Package**: `drago-ex/project-install`
- **Type**: drago-tools-resource
- **Purpose**: Application installation package.
- **PHP**: >=8.3 <9
- **Runtime dependencies**: drago-ex/bootstrap (^2.0), drago-ex/translator (^4.0.1), drago-ex/migration (^1.0), drago-ex/application (^4.0), drago-ex/component (^3.0), drago-ex/form (^3.0), drago-ex/database (^4.0 || ^5.0), drago-ex/project-docker (^1.0), drago-ex/project-docker-db (^1.1), drago-ex/project-front (^3.0), drago-ex/project-auth (^1.0 || ^2.0), drago-ex/project-backend (^1.0), drago-ex/project-backend-ui (^1.0), drago-ex/project-settings (^1.1 || ^2.0)
- **License**: MIT

## Repository Layout
- `resources/`: application resources contributed to the consuming Drago project.
- `src/`: package source code.
- `.github/`: CI workflows; keep their PHP versions and commands consistent with Composer and the package scripts.
- `composer.json`: package metadata, dependency constraints, autoloading, and available Composer scripts.

## Install and Application Mode
- `resources/www/index.php` boots the install container (`bootInstallApplication()`) until `Install\InstallLock::exists()` finds `local/installed.lock`, then the application container. `local/` (project root) holds the files the installer writes, `db.neon` and the lock (user, 2026-10-30): never `app/`, which stays read-only (in Docker it is in the image and lost on rebuild), never `temp/`, which can be cleared (a lost lock while the database is down would hand the installer to anyone). project-docker-db copies its development `db.neon` there and mounts the directory into the container; The installer container loads `local/db.neon` by `addConfig()`, the application container searches `app/` and `local/` with `addFindConfig()` like the skeleton project (user, 2026-10-30). After rewriting `db.neon`, `DatabaseFactory` deletes `temp/cache/nette.configurator`: without debug mode Nette does not notice a changed config and the next steps would use the old connection (not the whole `temp/cache`, it holds the wizard step).
- `resources/app/Bootstrap.php`: the web, console and `nette/mcp-inspector` entry points share `setupContainer()`, whose RobotLoader excludes `app/Presentation/Install`; Nette also finds presenters through it, so the installer must stay out of those containers.
- `Bootstrap.php`, `app.neon` and `www/` are copied with `replace-once`, so changes here do not reach existing projects automatically; mention needed manual steps in `readme.md`.
- `MigrationService` runs the list of `Drago\Migration\MigrationFiles::forProject()` (package migrations straight from `vendor/` by `migration-priority`, then the project `migrations/`) through `Drago\Migration\MigrationRunner` (services in `Install/conf.neon`), exactly like `vendor/bin/migration db:migrate --packages`. The installer has NOTHING about permissions (user, 2026-10-27): no project-permission require, no admin role, no `migrations-permission/`. Instead two neutral extension points (user's idea, like `sidebar.badge` of backend-ui): `MigrationService` gets `sources: tagged(install.migrations)` (callables `(string $projectRoot): list<{file, package, priority}>`, spliced after the package migrations and before the project ones, tested) and `InstallPresenter` gets `accountHandlers: tagged(install.account)` (callables `(int $userId)`, run in the account form onSuccess after the sign-up insert). drago-ex/project-preset-permission uses them with `Install/PermissionMigrations.php`, `Install/AdminRole.php` and `Install/permission.neon` (the installer container loads neons and classes of `app/Presentation/Install/`); it copies no installer file. Files are identified by their path relative to the project root, and only a file from that list can run. The wizard JS treats `skipped` as success.
- Wizard steps: any failure of the database step adds a form error (otherwise the wizard would go on without `local/db.neon`); the website step replaces its `settings` rows (the name is the primary key of `settings`), so it can run again; the finish redirects to `<base path>admin` by URL, as the installer container does not know the administration presenters.
- Tests: `composer tester`; `tests/bootstrap.php` autoloads `App\` classes from `resources/app`, then from `.dev-resource/app` (resources of the dependencies, installed by `drago-install --dev`).

## Dependencies

Composer constraints describe the package contract. Before changing integration behavior, inspect the source and the actual call sites in this package; do not infer runtime usage solely from dependency declarations.
Development dependencies include phpstan/phpstan-nette (^2.0), drago-ex/project-tools (^1.0), nette/tester (^2.5).

## Essential Commands

```bash
composer phpstan                # phpstan analyse
composer tester                 # tester tests -s -C
```

## Installation Hooks

Composer install and update hooks invoke Drago project setup/cleanup commands. Inspect those scripts and their effects before invoking Composer operations that trigger them; they may modify generated project files.

## Conventions & Rules

- Preserve backward compatibility unless the task explicitly permits a breaking change. Check callers and public signatures before changing behavior.
- Follow the existing PHP style: strict types, explicit parameter and return types, tabs for indentation, and single-quoted strings where practical. Match nearby code when a convention is unclear.
- Keep package-specific configuration, resources, and migrations in their established directories. Do not edit generated or consuming-project files without confirming they are part of this repository’s intended output.
- Add or update focused tests for behavioral changes when this package has a runnable test setup. Do not invent test commands; use only the scripts and configuration present here.
- Update README examples and Composer metadata when a public API or supported requirement changes.
