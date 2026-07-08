# Phresto Developer Documentation

This folder contains internal documentation for contributors and anyone who needs to understand how Phresto works under the hood.

## Files

- [architecture.md](architecture.md) — project layout, core abstractions, and request lifecycle
- [routing.md](routing.md) — how HTTP requests are resolved to classes and methods
- [models.md](models.md) — `Model` and `MySQLModel` internals
- [controllers.md](controllers.md) — `Controller`, `ModelController`, and `CustomModelController`
- [config.md](config.md) — INI configuration system
- [modules.md](modules.md) — module discovery and autoloading
- [database.md](database.md) — `DBConnector` / `MySQLConnector`
- [container.md](container.md) — reflection-based service container
- [views.md](views.md) — legacy HTML templating system

## Audience

These documents assume you are comfortable reading PHP and understand the framework's public API. If you are building an application with Phresto, start with the [SDK documentation](../sdk/README.md).
