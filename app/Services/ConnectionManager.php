<?php

namespace App\Services;

use App\Services\Connections\ConnectionInterface;
use App\Services\Connections\MdwConnection;
use Illuminate\Support\Facades\Config;

class ConnectionManager {
  public function register() : void {
    $this->setConnection(new MdwConnection());
  }

  public function setConnection(MdwConnection $interface) : void {
    $defaultConfig = array();

    $connectionName = $interface->getName();

    $defaultConfig['driver'] = 'mariadb';
    $defaultConfig['host'] = $interface->getHost();
    $defaultConfig['port'] = $interface->getPort();
    $defaultConfig['database'] = $interface->getDatabase();
    $defaultConfig['username'] = $interface->getUserName();
    $defaultConfig['password'] = $interface->getPassword();

    Config::set('database.connections.' . $connectionName, $defaultConfig);
  }
}