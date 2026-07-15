# OpenAPI Specification

Phresto generates a cached OpenAPI 3.0 specification that describes the whole API. You can use it with Swagger UI, Postman, code generators, or any OpenAPI-compatible tool.

## Where the spec lives

The framework writes the spec to:

```
config/openapi.json
```

It is rebuilt automatically in `env=dev` and can be rebuilt manually in production with:

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
- Individual query parameters (`where`, `order`, `limit`, `offset`, `fields`) on model collection endpoints. `where` is declared as `deepObject` with `explode: true` so Swagger UI renders it as a nested key/value editor.
- Related-model subpaths such as `/product/{id}/reviews`.
- `HEAD` operations on collections that return the record count in `X-Count`.
- Paths sorted alphabetically.

## Using the spec

Because the spec is standard OpenAPI 3.0, you can import it into:

- Swagger UI — interactive documentation.
- Postman / Insomnia — generate collections automatically.
- OpenAPI code generators — generate client SDKs in many languages.
- Any documentation tool that supports OpenAPI.

## Swagger UI

If the `swagger-api/swagger-ui` Composer package is installed and the `swagger` module is enabled, `GET /swagger` serves Swagger UI configured to load `/openapi`. Click **Authorize** and supply a JWT from `POST /user/authenticate` as `Bearer <token>`.

## Securing the spec endpoint

`/openapi` is a normal framework endpoint, so it uses the same permission system as any other route. Restrict access through profiles and permissions if the spec contains sensitive implementation details.
