<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Processed extends Model {
  protected $guarded = [];

  protected $casts = [
    'started_at' => 'datetime:Y-m-d H:i:s.v',
    'start_state' => 'integer',

    'ended_at' => 'datetime:Y-m-d H:i:s.v',
    'end_state' => 'integer',

    'alarm_started_at' => 'datetime:Y-m-d H:i:s.v',
    'alarm_ended_at' => 'datetime:Y-m-d H:i:s.v',

    'created_at' => 'datetime:Y-m-d H:i:s.v',
    'updated_at' => 'datetime:Y-m-d H:i:s.v',
  ];
}