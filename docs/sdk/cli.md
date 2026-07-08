# CLI Commands

Phresto provides a small CLI at `vendor/bin/phresto` (also copied into `bin/phresto` in older setups). It must be run from the project root.

## Available options

```bash
vendor/bin/phresto -n        # --new     Create a new Phresto project
vendor/bin/phresto -up       # --upgrade Update the framework and built-in modules
vendor/bin/phresto -i name   # --install Install a module (composer package)
vendor/bin/phresto -u name   # --update  Update a module (composer package)
vendor/bin/phresto -m        # --modules Update config/modules.ini
vendor/bin/phresto -h        # --help    Show help
```

## Create a new project

```bash
mkdir my-api
cd my-api
composer require phresto/phresto
vendor/bin/phresto -n
```

The CLI copies the `template/` directory into the current folder, prompts for a database connection, and writes `config/db.ini`. It also runs `bower install` to fetch frontend assets for Explorer and Admin.

## Upgrade an existing project

```bash
vendor/bin/phresto -up
```

This runs `composer update phresto/phresto` and re-copies template files. It preserves existing `.ini`, `lang/`, `temp/`, and `.htaccess` files.

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

In `env=dev` this happens automatically on every request, but it is good practice to refresh it after deploying to production.

## Writing a custom module package

A reusable module package should:

1. Be a Composer package with a PSR-4 autoloader (or none, since Phresto autoloads module classes by file name).
2. Include a `modules/<name>/` directory with `controller/`, `model/`, `class/`, `view/`, `config/`, `static/`, and `lang/` as needed.
3. Ship with a `README` documenting its endpoints and required config keys.
