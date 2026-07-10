<?php

declare(strict_types=1);

use Phresto\Interf\Migration;
use Phresto\Modules\Model\permission;
use Phresto\Modules\Model\profile;
use Phresto\Modules\Model\user;

// class name must follow pattern:
// Migration_{script file name}
class Migration_initial_user_setup implements Migration
{
    public function getTime()
    {
        // timestamp when script file was created
        // it is used to determine correct order of
        // running the scripts
        // run `php -r 'echo time()."\n";'` in cli to get current timestamp

        return 1700000000; // !!!!!! CHANGE ME !!!!!!!
    }

    public function getName()
    {
        // unique migration script name
        // saved to DB when migration runs
        return 'initial user setup';
    }

    public function run($db)
    {
        // migration script content here
        // use $db object to access database

        $visitor = new profile();
        $visitor->name = 'visitor';
        $visitor->save();

        $user = new profile();
        $user->name = 'user';
        $user->save();

        $admin = new profile();
        $admin->name = 'admin';
        $admin->save();

        $this->addPermission($visitor->getIndex(), 'user/authenticate', 'post', true);
        $this->addPermission($visitor->getIndex(), 'user/register', 'post', true);
        $this->addPermission($visitor->getIndex(), 'user/current', 'get', true);
        $this->addPermission($user->getIndex(), '*', '*', true);
        $this->addPermission($admin->getIndex(), '*', '*', true);

        $adminUser = new user();
        $adminUser->email = 'admin@localhost';
        $adminUser->name = 'Administrator';
        $adminUser->password = 'admin';
        $adminUser->status = 2;
        $adminUser->profile = $admin->getIndex();
        $adminUser->save();
    }

    protected function addPermission($profileId, $route, $method, $allow)
    {
        $permission = new permission();
        $permission->profile = $profileId;
        $permission->route = $route;
        $permission->method = $method;
        $permission->allow = $allow;
        $permission->save();
    }

    public function rollback($db)
    {
        // rollback script here
        // use $db object to access database

        $db->exec('DELETE FROM `user` WHERE `email` = :email', [ 'email' => 'admin@localhost' ]);
        $db->exec('DELETE FROM `permission`');
        $db->exec('DELETE FROM `profile`');
    }
}
