# Explorer and Admin Tools

Phresto includes two browser-based modules to help develop and manage applications.

## Explorer

URL: `/explorer`

Explorer discovers all controllers and models in the application and renders an interactive test UI. For each endpoint it shows:

- HTTP verbs
- URL parameters
- Request parameters
- A form to make requests
- JSON output

### How it works

1. The page loads Foundation and AngularJS assets.
2. `explorerApp.js` calls `GET /explorer/routes`.
3. The `routes_get` controller method invokes `Phresto\Modules\explorer::getRoutes()`.
4. `getRoutes()` reads `config/modules.ini`, autoloads each controller and model, and calls `discover()` to extract endpoint metadata.

### Using Explorer

1. Open `/explorer` in a browser.
2. Browse the accordion of endpoints.
3. Fill in id fields, URL parameters, query string, and request body.
4. Click **make request** to see the JSON response.

Explorer respects permissions, so you may need to authenticate first via `/user/authenticate`.

## Admin

URL: `/admin`

Admin provides tools for managing permissions and inspecting models.

### Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /admin` | Renders the admin UI. |
| `GET /admin/permissions/:profileId` | Returns all routes with the current permission state for a profile. |
| `GET /admin/models` | Returns discovered model endpoints. |

### Managing permissions

The admin UI calls `GET /admin/permissions/:profileId` and renders a matrix of routes × HTTP methods. Saving toggles creates or updates rows in the `permission` model.

### Securing the tools

By default the bundled `admin` controller uses the same `Controller::auth()` permission check. Make sure only trusted profiles have access to `/admin` and `/explorer`.
