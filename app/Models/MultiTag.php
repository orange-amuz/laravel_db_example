<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MultiTag extends Model {
  protected $connection = 'mdw';
  protected $table = 'multitag';

  public $incrementing = false;
  public $timestamps = false;

  protected $casts = [
    'EventTime' => 'datetime:Y-m-d H:i:s.v',
    'TAG_Value' => 'integer',
  ];
}