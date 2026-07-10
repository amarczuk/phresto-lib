<?php

declare(strict_types=1);

namespace Phresto\Auth;

use DateTime;
use Phresto\Modules\Model\revokedtoken;

/**
 * Two-level revocation cache for JWT ids (jti).
 *
 * The database keeps the canonical list of revoked tokens. A local file cache
 * is kept in front of it so that authentication checks do not hit the DB on
 * every request.
 */
class RevocationCache
{
    protected static $memory = [];

    /**
     * Check whether a token id has been revoked.
     *
     * @param string $jti
     * @return bool
     */
    public static function isRevoked(string $jti): bool
    {
        if (isset(self::$memory[$jti])) {
            return self::$memory[$jti];
        }

        $cache = self::readCache();
        if (isset($cache[$jti])) {
            if ($cache[$jti] > time()) {
                self::$memory[$jti] = true;

                return true;
            }
            self::remove($jti);

            return false;
        }

        self::$memory[$jti] = false;

        return false;
    }

    /**
     * Revoke a token until its expiration timestamp.
     *
     * @param string $jti
     * @param int    $expiresAt Unix timestamp
     */
    public static function revoke(string $jti, int $expiresAt): void
    {
        self::$memory[$jti] = true;

        if (class_exists(revokedtoken::class)) {
            $revoked = new revokedtoken();
            $revoked->jti = $jti;
            $revoked->expires = (new DateTime())->setTimestamp($expiresAt);
            $revoked->save();
        }

        $cache = self::readCache();
        $cache[$jti] = $expiresAt;
        self::writeCache($cache);
    }

    /**
     * Remove a token id from the cache.
     *
     * @param string $jti
     */
    public static function remove(string $jti): void
    {
        unset(self::$memory[$jti]);
        $cache = self::readCache();
        unset($cache[$jti]);
        self::writeCache($cache);
    }

    /**
     * Clean expired entries from the cache file.
     */
    public static function clean(): void
    {
        $now = time();
        $cache = self::readCache();
        $cache = array_filter($cache, function ($expiresAt) use ($now) {
            return $expiresAt > $now;
        });
        self::writeCache($cache);
    }

    protected static function readCache(): array
    {
        $file = self::cacheFile();
        if (!is_file($file)) {
            return [];
        }

        $data = json_decode(file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    protected static function writeCache(array $cache): void
    {
        $file = self::cacheFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents($file, json_encode($cache), LOCK_EX);
    }

    protected static function cacheFile(): string
    {
        return PHRESTO_ROOT . '/config/token_revocation_cache.json';
    }
}
