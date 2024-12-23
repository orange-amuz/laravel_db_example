<?php

namespace App\Services\Connections;

use App\Services\Connections\ConnectionInterface;

class MdwConnection {
  static public function getName() : string {
    return 'mdw';
  }

  static public function getHost() : string {
    return '127.0.0.1';
  }

  static public function getPort() : string {
    return '3306';
  }

  static public function getDatabase() : string {
    return 'learn';
  }

  static public function getUserName() : string {
    return 'root';
  }

  static public function getPassword() : string {
    return '1212';
  }
}