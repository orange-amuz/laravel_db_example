<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlarmHistoryIndexed extends Model {
  protected $guarded = [];

  protected $casts = [
    'event_time' => 'datetime:Y-m-d H:i:s.v',
  ];
}