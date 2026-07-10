<?php

declare(strict_types=1);

namespace Phresto\Modules\Model;

use DateTime;
use Phresto\MySQLModel;

class user extends MySQLModel
{
    public const CLASSNAME = __CLASS__;

    public const DB = 'mysql';
    public const NAME = 'user';
    public const INDEX = 'id';
    public const COLLECTION = 'user';

    protected static $_fields = [ 'id' => 'int',
                                  'email' => 'string',
                                  'password' => 'string',
                                  'name' => 'string',
                                  'status' => 'int',
                                  'created' => 'DateTime',
                                  'last_login' => 'DateTime',
                                  'profile' => 'int',
                                ];

    protected static $_defaults = [ 'status' => 1, 'created' => '', 'last_login' => '' ];

    protected static $_relations = [
        'profile' => [
            'type' => 'n:1',
            'model' => 'profile',
            'field' => 'id',
            'index' => 'profile',
            'dbactions' => 'ON UPDATE CASCADE ON DELETE SET NULL',
        ],
    ];

    protected function image_value()
    {
        return '//www.gravatar.com/avatar/' . md5((string) $this->email) . '?d=retro';
    }

    protected function saveFilter()
    {
        $this->email_md5 = md5((string) $this->email);
        $current = $this->password;
        $initial = $this->_initial['password'] ?? null;
        if (!empty($current) && $initial !== $current) {
            $this->password = password_hash((string) $current, PASSWORD_DEFAULT);
        }
    }

    protected function default_created()
    {
        return new DateTime();
    }

    protected function filterJson($fields)
    {
        if (isset($fields['password'])) {
            $fields['password'] = '*';
        }
        if (isset($fields['email'])) {
            $fields['image'] = $this->image;
        }

        return $fields;
    }
}
