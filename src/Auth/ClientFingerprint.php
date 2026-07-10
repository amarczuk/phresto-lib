<?php

declare(strict_types=1);

namespace Phresto\Auth;

/**
 * Build a stable, version-agnostic client fingerprint from request headers.
 *
 * The goal is to bind a token to a client class (browser family, OS family,
 * client type) and a stable language preference without tying it to a specific
 * user-agent version string. We deliberately ignore the version segment and
 * platform details that change on every update.
 */
class ClientFingerprint
{
    /**
     * Compute a fingerprint for the current request.
     *
     * @param array $headers Request headers
     * @return string
     */
    public static function fromHeaders(array $headers): string
    {
        $ua = $headers['User-Agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $lang = $headers['Accept-Language'] ?? $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        return self::hash($ua, $lang);
    }

    /**
     * Compute a fingerprint from raw inputs. Useful in tests.
     *
     * @param string $userAgent
     * @param string $acceptLanguage
     * @return string
     */
    public static function hash(string $userAgent, string $acceptLanguage): string
    {
        $parts = [
            self::clientClass($userAgent),
            self::osFamily($userAgent),
            strtolower(trim($acceptLanguage)),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Extract a coarse client class from a User-Agent string.
     *
     * @param string $userAgent
     * @return string
     */
    public static function clientClass(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);
        $clients = [
            'chrome' => 'chrome',
            'firefox' => 'firefox',
            'safari' => 'safari',
            'edge' => 'edge',
            'opr' => 'opera',
            'opera' => 'opera',
            'curl' => 'curl',
            'wget' => 'wget',
            'python-requests' => 'python-requests',
            'postman' => 'postman',
            'okhttp' => 'okhttp',
        ];
        foreach ($clients as $needle => $class) {
            if (mb_strpos($userAgent, $needle) !== false) {
                return $class;
            }
        }

        return 'unknown';
    }

    /**
     * Extract a coarse OS family from a User-Agent string.
     *
     * @param string $userAgent
     * @return string
     */
    public static function osFamily(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);
        $oses = [
            'windows' => 'windows',
            'macintosh' => 'macos',
            'mac os' => 'macos',
            'linux' => 'linux',
            'android' => 'android',
            'iphone' => 'ios',
            'ipad' => 'ios',
        ];
        foreach ($oses as $needle => $family) {
            if (mb_strpos($userAgent, $needle) !== false) {
                return $family;
            }
        }

        return 'unknown';
    }
}
