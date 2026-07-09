<?php

declare(strict_types=1);

namespace Phresto\Modules\Controller;

use Phresto\CustomModelController;
use Phresto\Response;

/**
* Additional token's REST endpoints
*/
class token extends CustomModelController
{
    public const CLASSNAME = __CLASS__;
    public const MODELCLASS = 'Phresto\\Modules\\Model\\token';

    protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];

    /**
    * Delete expired tokens
    */
    public function clean_get()
    {
        $token = static::MODELCLASS;
        $token::cleanExpired();

        return Response::json([ 'ok' => true ]);
    }
}
