# Module System and Autoloading

> **v2 baseline** — Modules no longer contain `view/` or `lang/` directories. The module scan also triggers an OpenAPI spec rebuild in development mode.

## Module directory structure

```
modules/
  example/
    controller/    -> Phresto\Modules\Controller\example
    model/       -> Phresto\Modules\Model\example
    middleware/  -> Phresto\Modules\Middleware\example
    class/       -> Phresto\Modules\example
    config/      -> INI overrides
    static/      -> JS/CSS
```

## Module registry

`Utils::updateModules()` scans each module and writes `config/modules.ini`:

```ini
[example]
Controller[] = "foo.php"
Model[] = "bar.php"
Middleware[] = "baz.php"
class[] = "qux.php"
```

This registry is used by `Utils::autoload()` to resolve `Phresto\Modules\...` class names at runtime.

## Autoloader (`src/Utils.php`)

`Utils::autoload($className)` runs after the Composer autoloader. It only handles classes whose namespace starts with `Phresto\Modules\`.

Given `Phresto\Modules\Controller\foo`, it looks for `modules/<module>/controller/foo.php`.
Given `Phresto\Modules\Model\bar`, it looks for `modules/<module>/model/bar.php`.
Given `Phresto\Modules\Middleware\baz`, it looks for `modules/<module>/middleware/baz.php`.
Given `Phresto\Modules\qux`, it looks for `modules/<module>/class/qux.php`.

The first module containing the requested file wins. Because class names map directly to file names, a module cannot contain two files with the same base class name.

## Installing modules

The CLI supports installing modules distributed as Composer packages:

```bash
vendor/bin/phresto -i vendor/package
```

The command runs `composer install vendor/package` and copies `vendor/vendor/package/modules/*` into the project's `modules/` directory. During updates (`-u`), existing `.ini` files are preserved.

## Development mode

When `config/app.ini` contains `env=dev`, `Utils::registerAutoload()` deletes and regenerates `config/modules.ini` on every request. In v2 it also rebuilds `config/openapi.json` in dev mode. In production both registries are only rebuilt when explicitly triggered (e.g. by running `Utils::updateModules()` / `OpenApi::buildSpec()` or `vendor/bin/phresto -m` / `vendor/bin/phresto -d`).
