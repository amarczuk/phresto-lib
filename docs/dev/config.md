# Configuration System

Phresto stores configuration in INI files. The `Config` class reads, caches, merges, and writes these files.

## File locations

- Global config: `config/<name>.ini`
- Module config: `modules/<module>/config/<name>.ini`

The `PHRESTO_ROOT` constant points at the project root and is defined in `bootstrap.php`.

## `Config` API (`src/Config.php`)

```php
Config::getConfig( $name, $module = null );
Config::saveConfig( $name, $config, $module = null );
Config::delConfig( $name, $module = null );
Config::mockConfig( $name, $config, $module = null ); // for tests
Config::clearCache();
```

### Reading

When a module is specified, `getConfig()` first tries the module-specific file. If it does not exist, it falls back to the global file. Missing files return an empty array.

Parsed configs are stored in a static cache keyed by path.

### Merging

`Config::mergeConfigs($a, $b)` recursively merges two arrays, with `$b` taking precedence.

### Writing

`saveConfig()` serialises an array to INI sections. Values are quoted if strings, written as `true`/`false` for booleans, and arrays are emitted with `[]` syntax.

## Standard config files

| File | Purpose |
|------|---------|
| `config/app.ini` | Environment, debug, CORS, main module, token encryption password. |
| `config/db.ini` | Database connection definitions. |
| `config/view.ini` | HTML page metadata, CSS/JS includes, charset, language. |
| `config/modules.ini` | Auto-generated registry of module files. |
| `modules/*/config/view.ini` | Per-module view overrides. |
| `modules/user/config/social.ini` | OAuth keys for social login. |

## Example `config/app.ini`

```ini
[app]
mainmodule=
env=dev
debug=on
tokenEncryptionPass=phrestoToken
```

When `env=dev`, module discovery is refreshed on every request (see `Utils::registerAutoload()`).

## Example `config/db.ini`

```ini
[mysql]
type=mysql
host=localhost
user=root
passwd=
dbname=phresto
```

The section key (`mysql`) is the connection name referenced by `Model::DB`.
