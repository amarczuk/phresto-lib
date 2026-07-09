<?php

declare(strict_types=1);

namespace Phresto\Modules\Model;

use Phresto\MySQLModel;

class permission extends MySQLModel
{
    public const CLASSNAME = __CLASS__;

    public const DB = 'mysql';
    public const NAME = 'permission';
    public const INDEX = 'id';
    public const COLLECTION = 'permission';

    protected static $_fields = [ 'id' => 'int',
                                  'profile' => 'int',
                                  'route' => 'string',
                                  'method' => 'string',
                                  'allow' => 'boolean',
                                  'created' => 'DateTime',
                                ];

    protected static $_defaults = [ 'created' => '' ];

    protected static $_relations = [
        'profile' => [
            'type' => 'n:1',
            'model' => 'profile',
            'field' => 'id',
            'index' => 'profile',
        ],
    ];

    protected function default_created()
    {
        return new \DateTime();
    }
}
