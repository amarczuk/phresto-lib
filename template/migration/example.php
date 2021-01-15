<?php

// class name must follow pattern:
// Migration_{script file name}
class Migration_example implements Phresto\Interf\Migration {
  public function getTime() {
    // timestamp when script file was created
    // it is used to determine correct order of
    // running the scripts
    // run `php -r 'echo time()."\n";'` in cli to get current timestamp

    return 1603150493; // !!!!!! CHANGE ME !!!!!!!
  }

  public function getName() {
    // unique migration script name
    // saved to DB when migration runs
    return 'example migration';
  }

  public function run($db) {
    // migration script content here
    // use $db object to access database
  }

  public function rollback($db) {
    // rollback script here
    // use $db object to access database
  }
}
