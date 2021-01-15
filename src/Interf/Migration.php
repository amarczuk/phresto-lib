<?php
namespace Phresto\Interf;

interface Migration {
  // timestamp when migration was created (for running order)
  public function getTime();
  // friendly name (unique) of migration
  public function getName();
  // migration script
  public function run($db);
  // rollback script
  public function rollback($db);
}
