<?php

namespace App\Services;

use App\Models\MultiTag;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class CacheService {
  private static Collection $cache;
  private static array $tagTypes;

  public static function boot() {
    self::$cache = Collection::empty();
    self::$tagTypes = array();
  }

  public static function addToCache(Collection $groupedData) : Collection {
    return Collection::empty();
  }

  public static function getFromCache(MultiTag $multiTag) : Collection {
    $eqpId = $multiTag->getAttribute('EQPID');

    try {
      $grouped = MultiTag::query()
        ->where('EQPID', $eqpId)
        ->orderBy('EventTime', 'ASC')
        ->groupBy('TAG_Type')
        ->get();

      /** @var MultiTag $group */
      foreach($grouped as $group) {
        $tagType = $group->getAttribute('TAG_Type');

        dd();

        if(self::$tagTypes->keys()->contains($tagType)) {
          $index = self::$tagTypes->get($tagType);

          self::$cache[$eqpId][$tagType]->append($group);
        }
        else {

        }

        break;
      }
    } catch(Exception $e) {
      dd($e);
    }

    // if(self::$cache->keys()->contains($eqpId)) {
    //   return self::$cache->get($eqpId);
    // }
    // else {

    // }

    return Collection::empty();
  }
}