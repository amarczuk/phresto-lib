<?php
namespace Phresto;
use Phresto\Modules\Model\profile;
use Phresto\Modules\Model\user;
use Phresto\Modules\Model\permission;

require_once(__DIR__ . '/bootstrap.php');

$profiles = json_decode(file_get_contents(__DIR__ . '/profile.json'));
$permissions = json_decode(file_get_contents(__DIR__ . '/permission.json'));
$users = json_decode(file_get_contents(__DIR__ . '/user.json'));

foreach ($profiles as $profile) {
    $obj = new profile($profile);
    try {
        $obj->save();
    } catch(\Exception $e) {
        echo $e->getMessage() . "\n";
    }
}

foreach ($users as $user) {
    $obj = new user($user);
    try {
        $obj->save();
    } catch(\Exception $e) {
        echo $e->getMessage() . "\n";
    }
}

foreach ($permissions as $permission) {
    $obj = new permission($permission);
    try {
        $obj->save();
    } catch(\Exception $e) {
        echo $e->getMessage() . "\n";
    }
}
