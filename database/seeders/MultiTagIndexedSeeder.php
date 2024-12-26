<?php

namespace Database\Seeders;

use App\Models\MultiTag;
use App\Models\MultiTagIndexed;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class MultiTagIndexedSeeder extends Seeder
{
    static int $total = 0;
    static int $current = 0;

    /**
     * Run the database seeds.
     */
    public function run(Carbon | null $totalStartedAt): void
    {
        self::$total = MultiTag::query()
            ->whereIn('TAG_Type', ['Equipment_State', 'Auto_Stop', 'Manual_Stop', 'Down_MCC', 'Control_State'])
            ->count();
        self::$current = 0;

        dump("\n");

        MultiTag::query()
            ->whereIn('TAG_Type', ['Equipment_State', 'Auto_Stop', 'Manual_Stop', 'Down_MCC', 'Control_State'])
            ->orderBy('EventTime', 'ASC')
            ->chunk(3000, function(Collection $multiTags) use ($totalStartedAt) {
                $caches = [];

                foreach($multiTags as $multiTag) {
                    $cache = [
                        'equipment_id' => $multiTag->getAttribute('EQPID'),
                        'event_time' => Carbon::create($multiTag->getAttribute('EventTime'))->format('Y-m-d H:i:s.v'),
                        'tag_type' => $multiTag->getAttribute('TAG_Type'),
                        'tag_value' => $multiTag->getAttribute('TAG_Value'),
                        'var_code' => $multiTag->getAttribute('VarCode'),
                    ];

                    $caches[] = $cache;
                }

                MultiTagIndexed::query()->insert($caches);

                self::$current += $multiTags->count();

                dump([
                    abs(($totalStartedAt ?? Carbon::now())->diffInMilliSeconds(Carbon::now()) / 1000),
                    self::$current . '/' . self::$total. ' processed.',
                ]);
            });
    }
}
