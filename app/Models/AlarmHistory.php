<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlarmHistory extends Model {
  protected $connection = 'mdw';
  protected $table = 'alarm_history';

  public $incrementing = false;
  public $timestamps = false;

  protected $casts = [
    'EventTime' => 'datetime:Y-m-d H:i:s.v',
  ];
}