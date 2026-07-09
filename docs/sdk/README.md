# Phresto SDK Documentation

> **v2 baseline** — Phresto is now a JSON-only REST API framework. The Explorer/Admin UIs, HTML templating, and language files have been removed. Discovery is provided as a cached OpenAPI 3.0 spec at `/openapi`. Phresto requires PHP 8.0 or newer.

This folder contains documentation for developers building applications with Phresto. For internal implementation details, see the [developer documentation](../dev/README.md).

## Files

- [`getting-started.md`](getting-started.md) — installation, project setup, and first request
- [`models.md`](models.md) — defining data models and their REST endpoints
- [`controllers.md`](controllers.md) — creating custom controllers
- [`routing.md`](routing.md) — URL conventions and endpoint resolution
- [`permissions.md`](permissions.md) — authentication, profiles, and permissions
- [`javascript-sdk.md`](javascript-sdk.md) — browser helper for API calls
- [`openapi-spec.md`](openapi-spec.md) — the generated OpenAPI 3.0 spec
- [`migrations.md`](migrations.md) — database migration scripts
- [`cli.md`](cli.md) — Phresto CLI commands

## Audience

These documents are for end users of the framework: backend and frontend developers creating APIs with Phresto.
