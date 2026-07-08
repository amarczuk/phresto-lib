# Getting Started

Phresto is a PHP REST framework that turns models and controllers into HTTP endpoints by convention. This guide shows how to create a new project and make your first API call.

## Requirements

- PHP 7.0 or newer
- MySQL (for the default model backend)
- Composer
- Bower (used by the bundled admin/explorer UIs)

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
  view.ini
modules/           # bundled modules: user, explorer, admin
static/
view/
migration/
scripts/
bootstrap.php
.htaccess
```

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

## Create the database tables

Run the model generator script:

```bash
php scripts/create_models.php
```

This introspects every model and creates or alters the corresponding MySQL tables, indexes, and foreign keys.

## Run migrations (optional)

Place migration classes in `migration/` following the example in `migration/example.php`, then run:

```bash
php scripts/run_migrations.php
```

## Start the server

Use any PHP-compatible web server. For local development with PHP's built-in server:

```bash
php -S localhost:8000
```

Make sure `mod_rewrite` is enabled on Apache, or configure your server to rewrite all requests to `bootstrap.php?PHRESTOREQUESTPATH=<path>`.

## First request

The default root path shows `static/index.html`. The Explorer tool is available at:

```
GET http://localhost:8000/explorer
```

Explorer lists all discovered endpoints and lets you test them from the browser.

## Typical workflow

1. Create a model class in `modules/<module>/model/<name>.php`.
2. Optionally create a controller class for custom endpoints.
3. Run `vendor/bin/phresto -m` to refresh `config/modules.ini`.
4. Run `php scripts/create_models.php` to update the database schema.
5. Call the endpoints from your frontend using `phresto.js` or any HTTP client.
