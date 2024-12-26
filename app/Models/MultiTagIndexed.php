<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MultiTagIndexed extends Model {
  protected $guarded = [];

  protected $casts = [
    'event_time' => 'datetime:Y-m-d H:i:s.v',
    'tag_value' => 'decimal:2',
  ];
}