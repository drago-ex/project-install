# Drago Project install

Application installation package.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/drago-ex/project-install/blob/main/license)
[![PHP version](https://badge.fury.io/ph/drago-ex%2Fproject-install.svg)](https://badge.fury.io/ph/drago-ex%2Fproject-install)
[![Coding Style](https://github.com/drago-ex/project-install/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/project-install/actions/workflows/coding-style.yml)

## Requirements
- PHP >= 8.3
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
File copying is handled automatically by [drago-ex/project-installer](https://github.com/drago-ex/project-installer),
which must be installed in your project. Without it, copy the files manually according to the `copy` section
in this package's `composer.json`. To skip this package, set `"skip": true` under
`extra.drago-project.packages.<package-name>` in your root `composer.json`.

> ⚠️ This package uses the `replace` section, which means some files will be **overwritten if they already exist**.
> Avoid manual edits to those files — use the `skip` option if you need to manage them yourself.
