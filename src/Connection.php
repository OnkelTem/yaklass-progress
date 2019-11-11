<?php

namespace Yaklass;

class Connection extends \Doctrine\DBAL\Connection {

  public function connect() {
    // Exception for sqlite - we need to transparently create the database path
    $params = $this->getParams();
    if ($params['driver'] == 'pdo_sqlite') {
      if (!file_exists($dir = dirname($params['path']))) {
        mkdir($dir, 0755, TRUE);
      }
    }
    parent::connect();
  }

}
