# Drago Project install

[English](readme.md) | Čeština

Balíček pro instalaci aplikace.

[![PHP version](https://badge.fury.io/ph/drago-ex%2Fproject-install.svg)](https://badge.fury.io/ph/drago-ex%2Fproject-install)
[![Coding Style](https://github.com/drago-ex/project-install/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/project-install/actions/workflows/coding-style.yml)

## Požadavky

- PHP >= 8.3, < 9
- Nette Framework
- Composer
- Bootstrap
- Naja
- Node.js
- Základní balíčky Drago Project

## Instalace

```bash
composer require drago-ex/project-install
```

## Soubory projektu

Kopírování souborů obstarává automaticky [drago-ex/project-tools](https://github.com/drago-ex/project-tools),
který musí být v projektu nainstalovaný. Bez něj zkopírujte soubory ručně podle sekce `copy`
v `composer.json` tohoto balíčku. Pro přeskočení balíčku nastavte `"skip": true` v
`extra.drago-tools.packages.<package-name>` v kořenovém `composer.json`.

> Upozornění: Balíček používá sekci `replace`, takže některé soubory **přepíše, pokud už existují**.
> Tyto soubory ručně neupravujte; pokud je chcete spravovat sami, použijte volbu `skip`.

Nahrazované soubory (`app/Bootstrap.php`, `app/app.neon`, `www/`) se kopírují jen jednou (`replace-once`).
Novější verze balíčku je v existujícím projektu neaktualizují, takže změny z nových verzí
přenášejte ručně.

## Jak to funguje

Dokud aplikace není nainstalovaná, `www/index.php` spouští jen instalátor (`app/Presentation/Install`).
Průvodce otestuje připojení k databázi, zapíše `app/db.neon`, spustí migrace, nastaví web
a vytvoří účet administrátora. Kroky nejde přeskočit ani opakovat.

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
