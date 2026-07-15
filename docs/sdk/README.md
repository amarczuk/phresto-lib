# Phresto SDK Documentation

Phresto is a JSON-only REST API framework. Discovery is provided as a cached OpenAPI 3.0 spec at `/openapi`, and interactive documentation is available at `/swagger` when the Swagger UI module is enabled. Phresto requires PHP 8.0 or newer.

This folder contains documentation for developers building applications with Phresto. For internal implementation details, see the [developer documentation](../dev/README.md).

## Files

- [`getting-started.md`](getting-started.md) — installation, project setup, and first request
- [`models.md`](models.md) — defining data models and their REST endpoints
- [`controllers.md`](controllers.md) — creating custom controllers
- [`routing.md`](routing.md) — URL conventions and endpoint resolution
- [`permissions.md`](permissions.md) — authentication, profiles, and permissions
- [`javascript-sdk.md`](javascript-sdk.md) — browser helper for API calls
- [`openapi-spec.md`](openapi-spec.md) — the generated OpenAPI 3.0 spec and Swagger UI
- [`query-language.md`](query-language.md) — filtering, sorting, and paginating model collections
- [`migrations.md`](migrations.md) — database migration scripts
- [`cli.md`](cli.md) — Phresto CLI commands

## Audience

These documents are for end users of the framework: backend and frontend developers creating APIs with Phresto.
