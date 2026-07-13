<?php

declare(strict_types=1);

namespace Phresto\Modules\Controller;

use Phresto\Controller;
use Phresto\Exception\RequestException;

/**
 * Drop-in Swagger UI controller.
 *
 * Serves the Swagger UI shell at /swagger and proxies the bundled assets
 * from the swagger-api/swagger-ui Composer package at /swagger/asset/{name}.
 */
class swagger extends Controller
{
    public const CLASSNAME = __CLASS__;

    protected $routeMapping = [ 'asset_get' => [ 'name' => 0 ] ];

    protected function auth($methodName, $args = null)
    {
        return true;
    }

    public function get()
    {
        $html = '<!DOCTYPE html>' . PHP_EOL
            . '<html lang="en">' . PHP_EOL
            . '<head>' . PHP_EOL
            . '    <meta charset="UTF-8">' . PHP_EOL
            . '    <title>API Documentation</title>' . PHP_EOL
            . '    <link rel="stylesheet" type="text/css" href="/swagger/asset/swagger-ui.css" />' . PHP_EOL
            . '    <link rel="icon" type="image/png" href="/swagger/asset/favicon-32x32.png" sizes="32x32" />' . PHP_EOL
            . '    <link rel="icon" type="image/png" href="/swagger/asset/favicon-16x16.png" sizes="16x16" />' . PHP_EOL
            . '    <style>' . PHP_EOL
            . '      html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }' . PHP_EOL
            . '      *, *:before, *:after { box-sizing: inherit; }' . PHP_EOL
            . '      body { margin: 0; background: #fafafa; }' . PHP_EOL
            . '    </style>' . PHP_EOL
            . '</head>' . PHP_EOL
            . '<body>' . PHP_EOL
            . '    <div id="swagger-ui"></div>' . PHP_EOL
            . '    <script src="/swagger/asset/swagger-ui-bundle.js"></script>' . PHP_EOL
            . '    <script src="/swagger/asset/swagger-ui-standalone-preset.js"></script>' . PHP_EOL
            . '    <script>' . PHP_EOL
            . '      window.onload = function() {' . PHP_EOL
            . '        window.ui = SwaggerUIBundle({' . PHP_EOL
            . '          url: "/openapi",' . PHP_EOL
            . '          dom_id: "#swagger-ui",' . PHP_EOL
            . '          deepLinking: true,' . PHP_EOL
            . '          presets: [' . PHP_EOL
            . '            SwaggerUIBundle.presets.apis,' . PHP_EOL
            . '            SwaggerUIStandalonePreset' . PHP_EOL
            . '          ],' . PHP_EOL
            . '          plugins: [' . PHP_EOL
            . '            SwaggerUIBundle.plugins.DownloadUrl' . PHP_EOL
            . '          ],' . PHP_EOL
            . '          layout: "StandaloneLayout"' . PHP_EOL
            . '        });' . PHP_EOL
            . '      };' . PHP_EOL
            . '    </script>' . PHP_EOL
            . '</body>' . PHP_EOL
            . '</html>';

        return [
            'body' => $html,
            'content-type' => 'text/html; charset=utf-8',
            'code' => 200,
        ];
    }

    public function asset_get(string $name)
    {
        $name = basename($name);
        $file = PHRESTO_ROOT . '/vendor/swagger-api/swagger-ui/dist/' . $name;

        if ($name === '' || !file_exists($file)) {
            throw new RequestException('Not found', 404);
        }

        $types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'html' => 'text/html; charset=utf-8',
            'map' => 'application/json',
        ];
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        return [
            'body' => file_get_contents($file),
            'content-type' => $types[$ext] ?? 'application/octet-stream',
            'code' => 200,
        ];
    }
}
