# Drago Project install

Balíček pro instalaci aplikace.

[![PHP version](https://badge.fury.io/ph/drago-ex%2Fproject-install.svg)](https://badge.fury.io/ph/drago-ex%2Fproject-install)
[![Coding Style](https://github.com/drago-ex/project-install/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/project-install/actions/workflows/coding-style.yml)

## Požadavky

- PHP >= 8.3, < 9
- [Drago Project](https://github.com/drago-ex/project)

## Instalace

```bash
composer require drago-ex/project-install
```

## Soubory kopírované do projektu

Soubory do projektu kopíruje [drago-ex/project-tools](https://github.com/drago-ex/project-tools)
podle nastavení v `extra.drago-tools` v `composer.json` tohoto balíčku.

## Jak to funguje

Dokud aplikace není nainstalovaná, `www/index.php` spouští jen instalátor (`app/Presentation/Install`).
Průvodce otestuje připojení k databázi, zapíše `app/db.neon`, spustí migrace, nastaví web
a vytvoří účet administrátora. Kroky jdou v pořadí a nejde je přeskočit.

## Zámek instalace

Dokončením průvodce vznikne `app/installed.lock`; dokud existuje, běží místo instalátoru aplikace.
Na serveru ho ponechte a podle způsobu nasazení ho buď commitněte, nebo přidejte do `.gitignore`.

## Migrace

Instalátor spustí migrace všech nainstalovaných balíčků v pořadí podle `migration-priority` a potom
vlastní `migrations/` projektu, stejně jako `vendor/bin/migration db:migrate --packages`. Provedené soubory se zaznamenávají,
takže instalátor jde po chybě spustit znovu a pozdější migrace se spouštějí z konzole:

```bash
php vendor/bin/migration db:migrate --packages
```

## Konzole

`app/app.neon` registruje `drago-ex/migration` a `contributte/console`, takže fungují příkazy jako
`vendor/bin/migration`. V Dockeru je spouštějte jako webového uživatele, aby soubory v `temp/` měly
správného vlastníka:

```bash
docker compose exec -u www-data server php vendor/bin/migration list
```

## Rozšíření instalátoru

Jiný balíček může přidat vlastní kroky službou v `app/Presentation/Install/` (instalátor načítá její neon
soubory a třídy):

- štítek `install.migrations`: volatelný objekt, který dostane kořen projektu a vrátí další migrace; spustí se po
  migracích balíčků a před vlastními migracemi projektu,
- štítek `install.account`: volatelný objekt, který dostane ID nového administrátora.

```neon
services:
	- {factory: App\Presentation\Install\AccountSetup, tags: [install.account], autowired: no}
```
