<?php

declare(strict_types=1);

namespace Phresto\Auth;

use Phresto\Config;
use Phresto\Exception\RequestException;

/**
 * Minimal HS256 JWT implementation used for Phresto authentication tokens.
 *
 * Tokens are stateless: the payload carries the user id, profile, permissions
 * and a client fingerprint. The database only stores revoked token ids (jti).
 */
class Jwt
{
    /**
     * Encode a payload into a signed JWT.
     *
     * @param array  $payload
     * @param string $secret  Signing secret (falls back to app config)
     * @return string
     */
    public static function encode(array $payload, ?string $secret = null): string
    {
        $secret = $secret ?: self::getSecret();
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode($payload));
        $signature = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * Decode and verify a JWT.
     *
     * @param string $token        Raw JWT string
     * @param string $fingerprint  Expected client fingerprint
     * @param string $secret       Signing secret
     * @return array|null          Payload array or null on failure
     */
    public static function decode(string $token, string $fingerprint, ?string $secret = null): ?array
    {
        $secret = $secret ?: self::getSecret();
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($headerB64, $payloadB64, $signatureB64) = $parts;

        $signature = self::base64UrlDecode($signatureB64);
        $expected = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        if (empty($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        if (empty($payload['fp']) || !hash_equals($payload['fp'], $fingerprint)) {
            return null;
        }

        return $payload;
    }

    /**
     * Extract a Bearer token from request headers.
     *
     * @param array $headers
     * @return string|null
     */
    public static function extractToken(array $headers): ?string
    {
        if (!empty($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);

            return $token !== $headers['Authorization'] ? $token : null;
        }

        if (!empty($_COOKIE['prsid'])) {
            return $_COOKIE['prsid'];
        }

        return null;
    }

    public static function getSecret(): string
    {
        $conf = Config::getConfig('app');
        $secret = $conf['app']['jwtSecret'] ?? '';
        if (empty($secret)) {
            throw new RequestException('Missing jwtSecret in app config', 500);
        }

        return $secret;
    }

    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - mb_strlen($data) % 4) % 4), true);
    }
}
