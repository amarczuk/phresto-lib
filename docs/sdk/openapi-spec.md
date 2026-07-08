# OpenAPI Specification

> **v2 baseline** — The old Explorer and Admin browser UIs have been removed. Phresto now generates a cached OpenAPI 3.0 specification that describes the whole API.

## Where the spec lives

The framework writes the spec to:

```
config/openapi.json
```

It is rebuilt automatically in `env=dev` and can be rebuilt manually in production with:

```bash
vendor/bin/phresto -d
# or
vendor/bin/phresto -m
```

## Fetching the spec

| Request | Output |
|---------|--------|
| `GET /openapi` | OpenAPI 3.0 JSON |
| `GET /openapi?format=yaml` | OpenAPI 3.0 YAML |

## What the spec contains

`src/OpenApi.php` reflects all modules and produces:

- Info block (title, version, description from `config/app.ini` if present).
- A path item for every controller and model endpoint.
- HTTP methods derived from public/protected method names.
- Parameter definitions from method signatures and model `$_fields`.
- Schema objects from model field descriptors.
- Related-model subpaths such as `/product/{id}/reviews`.

## Using the spec

Because the spec is standard OpenAPI 3.0, you can import it into:

- Swagger UI — drop-in interactive documentation.
- Postman / Insomnia — generate collections automatically.
- OpenAPI code generators — generate client SDKs in many languages.
- Any documentation tool that supports OpenAPI.

## Per-controller discovery

`GET /<name>/discover` still works and returns the OpenAPI path-item fragment for that controller or model. This is useful when you only want the metadata for a single endpoint group.

## Securing the spec endpoint

`/openapi` is a normal framework endpoint, so it uses the same permission system as any other route. Make sure only trusted profiles have access if your spec contains sensitive implementation details.
