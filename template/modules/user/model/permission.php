<?php

namespace Phresto\Modules\Model;
use Phresto\MySQLModel;
use Phresto\Config;

class permission extends MySQLModel {
	const CLASSNAME = __CLASS__;

    const DB = 'mysql';
    const NAME = 'permission';
    const INDEX = 'id';
    const COLLECTION = 'permission';

    protected static $_fields = [ 'id' => 'int',
                                  'profile' => 'int',
                                  'route' => 'string',
                                  'method' => 'string',
                                  'allow' => 'boolean',
                                  'created' => 'DateTime'
                                ];
    protected static $_defaults = [ 'created' => '' ];
    protected static $_relations = [
        'profile' => [
            'type' => 'n:1',
            'model' => 'profile',
            'field' => 'id',
            'index' => 'profile'
        ]
    ];

    protected function default_created() {
        return new \DateTime();
    }

}
