<?php

declare(strict_types=1);

namespace Phresto\Modules\Controller;

use Phresto\Auth\RevocationCache;
use Phresto\CustomModelController;
use Phresto\Modules\Middleware\auth;
use Phresto\Response;

/**
 * Revoked-token blacklist endpoints.
 */
class token extends CustomModelController
{
    public const CLASSNAME = __CLASS__;
    public const MODELCLASS = 'Phresto\\Modules\\Model\\revokedtoken';

    protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];

    protected static $middlewares = [ auth::class ];

    /**
    * Delete expired revoked tokens from the database and cache.
    */
    public function clean_get()
    {
        $token = static::MODELCLASS;
        $token::cleanExpired();
        RevocationCache::clean();

        return Response::json([ 'ok' => true ]);
    }
}
