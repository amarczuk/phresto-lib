# Module System and Autoloading

Phresto applications are organised into modules under `modules/`. A module is a folder containing controllers, models, classes, views, configs, static assets, and language files.

## Module directory structure

```
modules/
  example/
    controller/    -> Phresto\Modules\Controller\example
    model/       -> Phresto\Modules\Model\example
    class/       -> Phresto\Modules\example
    view/        -> templates
    config/      -> INI overrides
    static/      -> JS/CSS
    lang/        -> PHP language files
```

## Module registry

`Utils::updateModules()` scans each module and writes `config/modules.ini`:

```ini
[example]
Controller[] = "foo.php"
Model[] = "bar.php"
class[] = "baz.php"
```

This registry is used by `Utils::autoload()` to resolve `Phresto\Modules\...` class names at runtime.

## Autoloader (`src/Utils.php`)

`Utils::autoload($className)` runs after the Composer autoloader. It only handles classes whose namespace starts with `Phresto\Modules\`.

Given `Phresto\Modules\Controller\foo`, it looks for `modules/<module>/controller/foo.php`.
Given `Phresto\Modules\Model\bar`, it looks for `modules/<module>/model/bar.php`.
Given `Phresto\Modules\baz`, it looks for `modules/<module>/class/baz.php`.

The first module containing the requested file wins. Because class names map directly to file names, a module cannot contain two files with the same base class name.

## Installing modules

The CLI supports installing modules distributed as Composer packages:

```bash
vendor/bin/phresto -i vendor/package
```

The command runs `composer install vendor/package` and copies `vendor/vendor/package/modules/*` into the project's `modules/` directory. During updates (`-u`), existing `.ini` files are preserved.

## Development mode

When `config/app.ini` contains `env=dev`, `Utils::registerAutoload()` deletes and regenerates `config/modules.ini` on every request. In production this only happens when explicitly triggered (e.g. by running `Utils::updateModules()` or `vendor/bin/phresto -m`).
