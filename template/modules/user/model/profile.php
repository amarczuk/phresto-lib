<?php

namespace Phresto\Modules\Model;
use Phresto\MySQLModel;
use Phresto\Config;

class profile extends MySQLModel {
	const CLASSNAME = __CLASS__;

    const DB = 'mysql';
    const NAME = 'profile';
    const INDEX = 'id';
    const COLLECTION = 'profile';

    protected static $_fields = [ 'id' => 'int',
                                  'name' => 'string',
                                  'created' => 'DateTime'
                                ];
    protected static $_defaults = [ 'created' => '' ];
    protected static $_relations = [
        'user' => [
            'type' => '1:n',
            'model' => 'user',
            'field' => 'profile',
            'index' => 'id'
        ],
        'permission' => [
            'type' => '1:n',
            'model' => 'permission',
            'field' => 'profile',
            'index' => 'id'
        ]
    ];

    protected function default_created() {
        return new \DateTime();
    }

}
