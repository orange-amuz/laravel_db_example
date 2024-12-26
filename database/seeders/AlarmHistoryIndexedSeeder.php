<?php

namespace Database\Seeders;

use App\Models\AlarmHistory;
use App\Models\AlarmHistoryIndexed;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class AlarmHistoryIndexedSeeder extends Seeder
{
    static int $total = 0;
    static int $current = 0;

    /**
     * Run the database seeds.
     */
    public function run(Carbon | null $totalStartedAt): void
    {
        self::$total = AlarmHistory::query()
            ->count();
        self::$current = 0;

        dump("\n");

        AlarmHistory::query()
            ->orderBy('EventTime', 'ASC')
            ->chunk(3000, function(Collection $alarmHistories) use ($totalStartedAt) {
                $caches = [];

                foreach($alarmHistories as $alarmHistory) {
                    $cache = [
                        'equipment_id' => $alarmHistory->getAttribute('EQPID'),
                        'event_time' => Carbon::create($alarmHistory->getAttribute('EventTime'))->format('Y-m-d H:i:s.v'),
                        'event_flag' => $alarmHistory->getAttribute('EventFlag'),
                        'alarm_code' => $alarmHistory->getAttribute('AlarmCode'),
                    ];

                    $caches[] = $cache;
                }

                AlarmHistoryIndexed::query()->insert($caches);

                self::$current += $alarmHistories->count();

                dump([
                    abs(($totalStartedAt ?? Carbon::now())->diffInMilliSeconds(Carbon::now()) / 1000),
                    self::$current . '/' . self::$total. ' processed.',
                ]);
        });
    }
}
