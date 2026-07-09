<?php

declare(strict_types=1);

namespace Phresto;

/**
 * Response helpers for Phresto v2.
 *
 * v2 removes HTML templating. The only proper framework output is JSON
 * (or YAML for the API spec). This class replaces the old View helper.
 */
class Response
{
    /**
     * Return a JSON response envelope used by bootstrap.php.
     */
    public static function json($response, $code = 200)
    {
        $debug = ob_get_contents();
        ob_clean();

        $conf = Config::getConfig('app');
        if (!empty($debug) && !empty($conf['app']['debug']) && $conf['app']['debug'] === 'on') {
            $debug = explode("\n", trim($debug));
            if (is_array($response) || is_object($response)) {
                $response = json_decode(json_encode($response), true);
            }
            if (is_array($response)) {
                $response['_debug_'] = $debug;
            } else {
                $response = [ '_data' => $response, '_debug_' => $debug ];
            }
        }

        return [
            'body' => json_encode($response, JSON_PRETTY_PRINT),
            'content-type' => 'application/json',
            'code' => (int) $code,
        ];
    }

    /**
     * Return a YAML response envelope used by bootstrap.php.
     */
    public static function yaml($response, $code = 200)
    {
        $debug = ob_get_contents();
        ob_clean();

        $conf = Config::getConfig('app');
        if (!empty($debug) && !empty($conf['app']['debug']) && $conf['app']['debug'] === 'on') {
            // Debug is kept outside the YAML document as a comment.
            $debug = explode("\n", trim($debug));
            $yaml = self::toYaml($response);
            $yaml .= "\n# debug: " . json_encode($debug);
        } else {
            $yaml = self::toYaml($response);
        }

        return [
            'body' => $yaml,
            'content-type' => 'application/yaml; charset=utf-8',
            'code' => (int) $code,
        ];
    }

    /**
     * Minimal YAML encoder sufficient for the OpenAPI spec.
     */
    public static function toYaml($data, $indent = 0)
    {
        $out = '';
        $prefix = str_repeat('  ', $indent);

        if (is_array($data)) {
            $isAssoc = Utils::is_assoc_array($data);
            if (empty($data)) {
                return '[]';
            }
            foreach ($data as $key => $value) {
                if ($isAssoc) {
                    $out .= "\n{$prefix}{$key}:";
                    if (is_array($value)) {
                        if (Utils::is_assoc_array($value)) {
                            $out .= self::toYaml($value, $indent + 1);
                        } else {
                            $out .= self::toYaml($value, $indent + 1);
                        }
                    } else {
                        $out .= ' ' . self::yamlScalar($value);
                    }
                } else {
                    $out .= "\n{$prefix}-";
                    if (is_array($value)) {
                        if (Utils::is_assoc_array($value)) {
                            $out .= self::toYaml($value, $indent + 1);
                        } else {
                            $out .= self::toYaml($value, $indent + 1);
                        }
                    } else {
                        $out .= ' ' . self::yamlScalar($value);
                    }
                }
            }
        } else {
            $out .= ' ' . self::yamlScalar($data);
        }

        return $indent === 0 ? ltrim($out) : $out;
    }

    private static function yamlScalar($value)
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            if (preg_match('/[\:\{\}\[\]\,\&\*\#\?\|\-\<\>\=\!\%\@\\]/', $value) || $value === '') {
                return '"' . str_replace('"', '\\"', $value) . '"';
            }

            return $value;
        }

        return json_encode($value);
    }
}
