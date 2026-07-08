# Phresto Internal Architecture

Phresto is a small PHP REST framework built around convention-over-configuration routing, an in-memory service container, and module-based autoloading. This document describes the project layout and how the core pieces fit together.

## Repository layout

```
phresto-lib/
├── bin/phresto              CLI entry point
├── composer.json            Package metadata (PSR-4 autoload: Phresto\ -> src/)
├── README.md
├── src/                     Framework core
│   ├── Config.php
│   ├── Container.php
│   ├── Controller.php
│   ├── CustomModelController.php
│   ├── DBConnector.php
│   ├── Model.php
│   ├── ModelController.php
│   ├── MySQLConnector.php
│   ├── MySQLModel.php
│   ├── Router.php
│   ├── Utils.php
│   ├── View.php
│   ├── XMLParser.php
│   ├── Exception/
│   └── Interf/
├── template/                Files copied into a new Phresto project
│   ├── bootstrap.php
│   ├── .htaccess
│   ├── static/
│   ├── view/
│   ├── config/
│   ├── modules/
│   ├── scripts/
│   └── migration/
└── test/                    Unit tests
```

## Request lifecycle

1. **HTTP request hits `.htaccess`**
   - Static files are served directly.
   - Everything else is rewritten to `bootstrap.php?PHRESTOREQUESTPATH=<original_path>`.

2. **`template/bootstrap.php` bootstraps the app**
   - Defines `PHRESTO_ROOT`.
   - Starts session and output buffering.
   - Registers Composer autoload, then `Phresto\Utils::autoload()` for module classes.
   - Sets the main view language.
   - Calls `Phresto\Router::route()` and prints the result.

3. **`Router::route()` parses the request**
   - Determines HTTP method, path segments, query string, body, and headers.
   - Handles CORS preflight if configured.
   - Resolves the first path segment to either:
     - A controller class: `Phresto\Modules\Controller\<name>`
     - A model class: `Phresto\Modules\Model\<name>` (wrapped in `ModelController`)
     - The configured `mainmodule` or `static/index.html` for the root path.

4. **Controller executes**
   - `Controller::getMethod()` uses reflection to pick the method matching the HTTP verb.
   - It binds route, body, and query parameters to method arguments.
   - `auth()` is called; on failure a 401 is thrown.
   - The method result is returned to the router.

5. **Response rendering**
   - If the controller returns an array with `content-type` and `body`, `bootstrap.php` sends that directly (used for JSON).
   - If it returns a string, it is echoed as-is (used for HTML views).

## Core abstractions

| Class | Responsibility |
|-------|----------------|
| `Router` | Parses HTTP requests, resolves endpoints, handles CORS and errors. |
| `Controller` | Base class for HTTP endpoints; discovers methods, injects params, enforces auth. |
| `ModelController` | Generic REST controller for any `Model` subclass. |
| `CustomModelController` | Convenience base for giving a model a dedicated controller class. |
| `Model` | Active-record-like entity with typed fields, defaults, relations, and JSON serialization. |
| `MySQLModel` | `Model` implementation backed by MySQL. |
| `DBConnector` / `MySQLConnector` | Database abstraction and connection management. |
| `Container` | Simple reflection-based factory/cache used to instantiate classes. |
| `Config` | Reads/writes INI configuration files and caches parsed values. |
| `Utils` | Module discovery and autoloading helpers. |
| `View` | Legacy HTML templating and JSON response helper. |

## Module system

Modules live under `modules/<module_name>/` and may contain:

- `controller/` — HTTP endpoint classes in namespace `Phresto\Modules\Controller`.
- `model/` — Data model classes in namespace `Phresto\Modules\Model`.
- `class/` — Supporting classes in namespace `Phresto\Modules`.
- `view/` — `.htm` templates.
- `config/` — Module-specific INI files.
- `static/` — Module-specific JS/CSS assets.
- `lang/` — PHP language files.

`Utils::updateModules()` scans these directories and writes a `config/modules.ini` registry. `Utils::autoload()` then resolves `Phresto\Modules\...` class names to the correct file at runtime.

## Class loading

Three autoloaders are involved:

1. **Composer PSR-4** — loads framework classes from `src/` (`Phresto\...`).
2. **`Utils::libAutoad`** — optional fallback loader for framework classes (rarely needed today).
3. **`Utils::autoload`** — resolves user-defined module classes based on `config/modules.ini`.

## Design notes

- The framework targets PHP 7.0+ and avoids external framework dependencies beyond `lusitanian/oauth`.
- Routing is based on class/method naming conventions rather than explicit route definitions.
- Models use static field descriptors rather than annotations or migrations for schema metadata.
- The `Container` is a very lightweight DI/factory with optional caching.
- Views and HTML templating are present but marked deprecated; most endpoints return JSON.
