<?php

declare(strict_types=1);

namespace Phresto\Modules\Model;

use DateTime;
use Phresto\MySQLConnector;
use Phresto\MySQLModel;

/**
 * Revoked-token blacklist.
 *
 * Phresto v2 uses stateless JWTs. The only token state kept in the database is
 * the revocation list, plus a file cache in front of it to avoid DB lookups on
 * every authenticated request.
 */
class revokedtoken extends MySQLModel
{
    public const CLASSNAME = __CLASS__;

    public const DB = 'mysql';
    public const NAME = 'revokedtoken';
    public const INDEX = 'id';
    public const COLLECTION = 'revokedtoken';

    protected static $_fields = [ 'id' => 'int',
                                  'jti' => 'string',
                                  'expires' => 'DateTime',
                                  'created' => 'DateTime',
                                ];

    protected static $_defaults = [ 'created' => '' ];

    protected function default_created()
    {
        return new DateTime();
    }

    /**
     * Delete revocation records whose expires date has passed.
     */
    public static function cleanExpired()
    {
        $sql = 'DELETE FROM ' . static::COLLECTION . ' WHERE `expires` IS NULL OR `expires` <= NOW();';
        $mysql = MySQLConnector::getInstance(static::DB);
        $mysql->query($sql, []);
    }
}
