# CLI Commands

> **v2 baseline** — Bower support has been removed. A new `-d`/`--discover` option rebuilds the OpenAPI spec.

Phresto provides a small CLI at `vendor/bin/phresto` (also copied into `bin/phresto` in older setups). It must be run from the project root.

## Available options

```bash
vendor/bin/phresto -n        # --new     Create a new Phresto project
vendor/bin/phresto -up       # --upgrade Update the framework and built-in modules
vendor/bin/phresto -i name   # --install Install a module (composer package)
vendor/bin/phresto -u name   # --update  Update a module (composer package)
vendor/bin/phresto -m        # --modules Update config/modules.ini and rebuild OpenAPI spec
vendor/bin/phresto -d        # --discover Rebuild config/openapi.json only
vendor/bin/phresto -h        # --help    Show help
```

## Create a new project

```bash
mkdir my-api
cd my-api
composer require phresto/phresto
vendor/bin/phresto -n
```

The CLI copies the `template/` directory into the current folder and prompts for a database connection. There is no frontend asset installation anymore.

## Upgrade an existing project

```bash
vendor/bin/phresto -up
```

This runs `composer update phresto/phresto` and re-copies template files. It preserves existing `.ini`, `migration/`, and `.htaccess` files.

## Install or update a module

Modules are Composer packages that expose a `modules/` directory:

```bash
vendor/bin/phresto -i vendor/package-name
vendor/bin/phresto -u vendor/package-name
```

Update preserves existing module `.ini` files.

## Refresh the module registry

After adding or removing files under `modules/`, regenerate the autoload registry:

```bash
vendor/bin/phresto -m
```

This also rebuilds `config/openapi.json`. In `env=dev` this happens automatically on every request, but it is good practice to refresh it after deploying to production.

The registry now also scans `modules/<module>/middleware/` for middleware classes.

## Rebuild the OpenAPI spec only

```bash
vendor/bin/phresto -d
```

Use this when you want to update the cached spec without regenerating `config/modules.ini`.

## Writing a custom module package

A reusable module package should:

1. Be a Composer package with a PSR-4 autoloader (or none, since Phresto autoloads module classes by file name).
2. Include a `modules/<name>/` directory with `controller/`, `model/`, `class/`, `config/`, and `static/` as needed.
3. Ship with a `README` documenting its endpoints and required config keys.
