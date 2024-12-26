<?php

namespace App\Console\Commands\Orange;

use App\Models\AlarmHistoryIndexed;
use App\Models\MultiTag;
use App\Models\MultiTagIndexed;
use App\Models\Processed;
use App\Services\CacheService;
use Carbon\Carbon;
use Database\Seeders\AlarmHistoryIndexedSeeder;
use Database\Seeders\MultiTagIndexedSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class Optimized extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orange:optimized';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Orange's optimized result";

    public static array $previousMultiTags;

    public static Collection $tagTypes;

    public static array $insertCache = array();

    public static Carbon $totalStartedAt;
    public static int $totalCount;
    public static int $currentCount;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', -1);

        Processed::query()->truncate();
        AlarmHistoryIndexed::query()->truncate();
        MultiTagIndexed::query()->truncate();

        self::$tagTypes = Collection::empty();
        self::$previousMultiTags = array();
        self::$totalCount = MultiTag::query()->where('TAG_Type', 'Equipment_State')->count();
        self::$currentCount = 0;
        self::$totalStartedAt = Carbon::now();

        // 데이터 전처리 To Local DB
        (new AlarmHistoryIndexedSeeder)->run(self::$totalStartedAt);
        (new MultiTagIndexedSeeder)->run(self::$totalStartedAt);


        // 1. 정합
        // 2. 속도

        // 본격적인 가공된 데이터 생성
        MultiTagIndexed::query()
            ->where('tag_type', 'Equipment_State')
            ->orderBy('event_time', 'ASC')
            ->chunk(3000, function(Collection $multiTags) {
                foreach($multiTags as $multiTag) {
                    $eqpId = $multiTag->getAttribute('equipment_id');

                    $previous = self::$previousMultiTags[$eqpId] ?? array();

                    /** @var Carbon $startedAt, $endedAt */
                    $startedAt = is_null($previous['ended_at'] ?? null) ? null : Carbon::create($previous['ended_at']);
                    $startState = $previous['end_state'] ?? null;
                    $endedAt = $multiTag->getAttribute('event_time');
                    $endState = $multiTag->getAttribute('tag_value');

                    $maintainTime = is_null($startedAt) ? null : abs($startedAt->diffInMilliseconds($endedAt) / 1000);

                    $type = MultiTagIndexed::query()
                        ->where('equipment_id', $eqpId)
                        ->where('tag_type', 'Control_State')
                        ->where('event_time', '<=', $endedAt)
                        ->orderBy('event_time', 'DESC')
                        ->first()
                        ?->getAttribute('tag_value');

                    $previous = array_merge($previous, [
                        'equipment_id' => $eqpId,
                        'type' => $type,
                        'started_at' => $startedAt?->format('Y-m-d H:i:s.v'),
                        'start_state' => $startState,
                        'ended_at' => $endedAt->format('Y-m-d H:i:s.v'),
                        'end_state' => $endState,
                    ]);

                    $pauseReason = null;
                    $pauseType = null;
                    $pauseInterval = null;

                    $alarmStartedAt = null;
                    $alarmCode = null;
                    $alarmEndedAt = null;
                    $alarmMaintainTime = null;

                    if($startState == 3) {
                        $pause = MultiTagIndexed::query()
                            ->where('equipment_id', $eqpId)
                            ->whereIn('tag_type', ['Auto_Stop', 'Manual_Stop'])
                            ->where('event_time', '>', $startedAt->subSeconds(2))
                            ->where('event_time', '<=', $endedAt)
                            ->orderBy('event_time', 'ASC')
                            ->first();

                        $pauseReason = $pause?->getAttribute('TAG_Value');
                        $pauseType = $pause?->getAttribute('TAG_Type');

                        $pauseRange = MultiTagIndexed::query()
                            ->where('equipment_id', $eqpId)
                            ->where('tag_type', 'Down_MCC')
                            ->where('event_time', '>', $startedAt->subSeconds(2))
                            ->where('event_time', '<=', $endedAt)
                            ->orderBy('event_time', 'ASC')
                            ->first();

                        $pauseInterval = $pauseRange?->getAttribute('TAG_Value');

                        $alarm = AlarmHistoryIndexed::query()
                            ->where('equipment_id', $eqpId)
                            ->where('event_flag', 'S')
                            ->where('event_time', '>', $startedAt->subSeconds(2))
                            ->where('event_time', '<=', $endedAt)
                            ->orderBy('event_time', 'ASC')
                            ->first();
                        $alarmStartedAt = $alarm?->getAttribute('EventTime');
                        $alarmCode = $alarm?->getAttribute('AlarmCode');

                        if($alarmStartedAt != null) {
                            $alarmEndedAt = AlarmHistoryIndexed::query()
                                ->where('equipment_id', $eqpId)
                                ->where('alarm_code', $alarmCode)
                                ->where('event_flag', 'E')
                                ->where('event_time', '>=', $alarmStartedAt)
                                ->orderBy('evnet_time', 'ASC')
                                ->first()
                                ?->getAttribute('EventTime');
                        }

                        // TODO : $alarmEndedAt 이 null일 경우 스케줄러 작동하기

                        if($alarmStartedAt != null && $alarmEndedAt != null) {
                            $alarmMaintainTime = abs($alarmStartedAt->diffInMilliseconds($alarmEndedAt) / 1000);
                        }
                    }

                    $previous = array_merge($previous, [
                        'maintain_time' => $maintainTime,
                        'pause_type' => $pauseType,
                        'pause_reason' => $pauseReason,
                        'pause_interval' => $pauseInterval,
                        'alarm_started_at' => $alarmStartedAt?->format('Y-m-d H:i:s.v'),
                        'alarm_ended_at' => $alarmEndedAt?->format('Y-m-d H:i:s.v'),
                        'alarm_code' => $alarmCode,
                        'alarm_maintain_time' => $alarmMaintainTime,
                    ]);

                    self::$insertCache[] = $previous;

                    self::$previousMultiTags[$eqpId] = $previous;

                    dump([
                        self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000,
                        ++self::$currentCount . '/' . self::$totalCount,
                    ]);
                }

                Processed::query()->insert(self::$insertCache);

                self::$insertCache = array();

                // dump(self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000);
            });

        $totalEndedAt = Carbon::now();

        dump('total time : ' . abs(self::$totalStartedAt->diffInMilliseconds($totalEndedAt) / 1000));
    }
}
