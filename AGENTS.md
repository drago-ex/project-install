# Agent Guide

Use this file as package-specific guidance when working in this repository. Keep changes aligned with the existing public API, project conventions, and supported PHP range.

## Package Overview
- **Package**: `drago-ex/project-install`
- **Type**: drago-tools-resource
- **Purpose**: Application installation package.
- **PHP**: >=8.3 <9
- **Runtime dependencies**: drago-ex/bootstrap (^2.0), drago-ex/translator (^4.0), drago-ex/migration (^2.3), drago-ex/application (^3.0 || ^4.0), drago-ex/component (^2.0), drago-ex/form (^2.0 || ^3.0), drago-ex/database (^3.0 || ^4.0), drago-ex/project-docker (^2.0), drago-ex/project-docker-db (^2.0), drago-ex/project-front (^2.0), drago-ex/project-auth (^2.0), drago-ex/project-backend (^2.0), drago-ex/project-backend-ui (^2.0), drago-ex/project-settings (^1.0), drago-ex/project-permission (^1.0)
- **License**: MIT

## Repository Layout
- `resources/`: application resources contributed to the consuming Drago project.
- `src/`: package source code.
- `.github/`: CI workflows; keep their PHP versions and commands consistent with Composer and the package scripts.
- `composer.json`: package metadata, dependency constraints, autoloading, and available Composer scripts.

## Install and Application Mode
- `resources/www/index.php` boots the install container (`bootInstallApplication()`) until `Install\InstallLock::exists()` finds `app/installed.lock` (a lock in `temp/` from older versions is moved there), then the application container.
- `resources/app/Bootstrap.php`: the web, console and `nette/mcp-inspector` entry points share `setupContainer()`, whose RobotLoader excludes `app/Presentation/Install`; Nette also finds presenters through it, so the installer must stay out of those containers.
- `Bootstrap.php`, `app.neon` and `www/` are copied with `replace-once`, so changes here do not reach existing projects automatically; mention needed manual steps in `readme.md`.
- `MigrationService` runs the list of `Drago\Migration\MigrationFiles::forProject()` (package migrations straight from `vendor/` by `migration-priority`, then the project `migrations/`) through `Drago\Migration\MigrationRunner` (services in `Install/conf.neon`), exactly like `vendor/bin/migration db:migrate --packages`. With drago-ex/project-permission installed (`InstalledVersions::isInstalled()`), `permissionMigrations()` adds the `migrations-permission/` files of all installed packages (by `migration-priority`) after the package migrations and before the project ones; they are recorded under their package like `db:migrate vendor/<package>/migrations-permission` would. This is the only place that runs them automatically: drago-ex/migration stays independent of the installed packages (decided 2026-10-22), and projects without the installer import them by hand. Files are identified by their path relative to the project root, and only a file from that list can run. The wizard JS treats `skipped` as success.
- Wizard steps: any failure of the database step adds a form error (otherwise the wizard would go on without `db.neon`); the website step replaces its `settings` rows (the name is the primary key since project-settings 002), so it can run again; the finish redirects to `<base path>admin` by URL, as the installer container does not know the administration presenters.
- Tests: `composer tester`; `tests/bootstrap.php` autoloads `App\` classes from `resources/app`, then from `.dev-resource/app` (resources of the dependencies, installed by `drago-install --dev`).

## Dependencies

Composer constraints describe the package contract. Before changing integration behavior, inspect the source and the actual call sites in this package; do not infer runtime usage solely from dependency declarations.
Development dependencies include phpstan/phpstan-nette (^2.0), drago-ex/project-tools (^2.0).

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
