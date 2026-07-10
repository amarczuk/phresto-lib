# Getting Started

> **v2 baseline** — Phresto is now a JSON-only REST API framework. The bundled Explorer/Admin UIs, HTML templates, Bower setup, and language files are gone. Discovery is served as an OpenAPI 3.0 spec at `/openapi`.

Phresto is a PHP REST framework that turns models and controllers into HTTP endpoints by convention. This guide shows how to create a new project and make your first API call.

## Requirements

- PHP 8.0 or newer
- MySQL (for the default model backend)
- Composer

## Create a project

Install the framework with Composer in an empty directory:

```bash
composer require phresto/phresto
vendor/bin/phresto -n
```

The `-n` option copies the `template/` files into the current directory and prompts for a database connection.

## Project layout after creation

```
config/
  app.ini
  db.ini
modules/           # built-in module: user
static/            # static assets only
migration/
scripts/
bootstrap.php
.htaccess
```

There is no `view/`, `lang/`, or `config/view.ini` anymore.

## Configure the database

Edit `config/db.ini`:

```ini
[mysql]
type=mysql
host=localhost
user=root
passwd=
dbname=phresto
```

The section name (`mysql`) is the connection key used by models via `const DB = 'mysql'`.

## Set the JWT secret

Edit `config/app.ini` and replace the placeholder with a strong random secret:

```ini
[app]
jwtSecret=your-long-random-secret-here
```

Tokens are signed with this secret. If it leaks, attackers can forge tokens.

## Create the database tables

Run the model generator script:

```bash
php scripts/create_models.php
```

This introspects every model and creates or alters the corresponding MySQL tables, indexes, and foreign keys.

## Run migrations

The bundled `migration/initial_user_setup.php` seeds the `visitor`, `user` and `admin` profiles and creates a default admin user. Run it after `create_models.php`:

```bash
php scripts/run_migrations.php
```

Default admin credentials:

- email: `admin@localhost`
- password: `admin`

Change these after the first login.

## Start the server

Use any PHP-compatible web server. For local development with PHP's built-in server:

```bash
php -S localhost:8000
```

Make sure `mod_rewrite` is enabled on Apache, or configure your server to rewrite all requests to `bootstrap.php?PHRESTOREQUESTPATH=<path>`.

## First request

The framework only returns JSON. Test the built-in user model:

```bash
curl -X POST \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"secret"}' \
  http://localhost:8000/user/register
```

Or inspect the OpenAPI spec:

```bash
curl http://localhost:8000/openapi
curl "http://localhost:8000/openapi?format=yaml"
```

## Typical workflow

1. Create a model class in `modules/<module>/model/<name>.php`.
2. Optionally create a controller class for custom endpoints.
3. Run `vendor/bin/phresto -m` to refresh `config/modules.ini` and `config/openapi.json`.
4. Run `php scripts/create_models.php` to update the database schema.
5. Call the endpoints from your frontend using `phresto.js` or any HTTP client.

## Code style

All PHP files in a Phresto project use `declare(strict_types=1)` and follow PSR-12. If you installed dev dependencies, you can check and fix formatting with the provided Composer scripts:

```bash
composer cs-check
composer cs-fix
```
