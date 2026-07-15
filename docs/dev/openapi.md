# OpenAPI and Swagger UI

Phresto generates a cached OpenAPI 3.0 specification from the modules and serves it as JSON or YAML. An optional Swagger UI module provides interactive documentation.

## Where the spec lives

The framework writes the spec to:

```
config/openapi.json
```

It is rebuilt automatically when `config/app.ini` contains `env=dev`. In production, regenerate it with:

```bash
vendor/bin/phresto -o
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

- Info block (title and version from `config/app.ini`).
- A `bearerAuth` JWT security scheme applied globally.
- A path item for every controller and model endpoint.
- HTTP methods derived from public/protected method names.
- Path parameters only for method parameters declared in the signature.
- Query/body parameters from method signatures.
- Schema objects from model field descriptors.
- Related-model subpaths such as `/product/{id}/reviews`.
- `HEAD` operations on collections that return the record count in `X-Count`.
- Paths sorted alphabetically.

### Query parameters on model collections

`GET /{model}` and `HEAD /{model}` accept the full Phresto query language as query parameters:

| Parameter | Type | Purpose |
|-----------|------|---------|
| `where` | object | Field filters and logical groups, serialized as `deepObject` (e.g. `where[name]=foo`). |
| `order` | string | Comma-separated `field [ASC/DESC]`. |
| `limit` | integer | Maximum number of records. |
| `offset` | integer | Number of records to skip. |
| `fields` | string | Comma-separated field names to return. |

Example:

```bash
curl "http://localhost:8000/product?where[status]=1&where[price][<=]=100&order=created%20DESC&limit=10"
```

Complex filters such as `or` and `and` groups can be passed as JSON in the `where` parameter:

```bash
curl "http://localhost:8000/product?where={%22or%22:[{%22status%22:1},{%22and%22:[{%22price%22:{%22%3C=%22:50}},{%22in_stock%22:true}]}]}"
```

In Swagger UI the `where` parameter is rendered as a nested key/value editor because it uses `style: deepObject, explode: true`. All other collection parameters remain simple scalar inputs.

For the complete query syntax, see [`query-language.md`](query-language.md).

## Swagger UI

Install the drop-in Swagger UI package:

```bash
composer require swagger-api/swagger-ui
```

Add the `swagger` module by creating `modules/swagger/controller/swagger.php`. The built-in template module provides a ready-made controller that serves the UI at `/swagger` and proxies assets from `vendor/swagger-api/swagger-ui/dist/` at `/swagger/asset/{name}`.

Once enabled, open `http://localhost/swagger` to explore the API. Click the **Authorize** button and enter a JWT obtained from `POST /user/authenticate` as `Bearer <token>`.

## Securing the spec endpoint

`/openapi` is a normal framework endpoint and uses the same permission system as any other route. Restrict access through profiles and permissions if the spec contains sensitive details.
