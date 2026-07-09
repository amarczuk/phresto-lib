<?php

declare(strict_types=1);

namespace Phresto\Modules\Model;

use Phresto\MySQLModel;

class profile extends MySQLModel
{
    public const CLASSNAME = __CLASS__;

    public const DB = 'mysql';
    public const NAME = 'profile';
    public const INDEX = 'id';
    public const COLLECTION = 'profile';

    protected static $_fields = [ 'id' => 'int',
                                  'name' => 'string',
                                  'created' => 'DateTime',
                                ];

    protected static $_defaults = [ 'created' => '' ];

    protected static $_relations = [
        'user' => [
            'type' => '1:n',
            'model' => 'user',
            'field' => 'profile',
            'index' => 'id',
        ],
        'permission' => [
            'type' => '1:n',
            'model' => 'permission',
            'field' => 'profile',
            'index' => 'id',
        ],
    ];

    protected function default_created()
    {
        return new \DateTime();
    }
}
