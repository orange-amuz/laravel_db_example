<?php

namespace App\Console\Commands;

use App\Models\AlarmHistory;
use App\Models\MultiTag;
use App\Models\Processed;
use App\Services\CacheService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orange:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Orange's result";

    public static array $previousMultiTags;


    public static array $insertCache = array();

    public static Carbon $totalStartedAt;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', -1);

        self::$previousMultiTags = array();

        CacheService::boot();

        Processed::query()->truncate();

        self::$totalStartedAt = Carbon::now();

        // use DP?

        // 1. 정합
        // 2. 속도

        MultiTag::query()
            ->where('TAG_Type', 'Equipment_State')
            ->orderBy('EventTime', 'ASC')
            ->chunk(1000, function(Collection $multiTags) {
                foreach($multiTags as $multiTag) {
                    $eqpId = $multiTag->getAttribute('EQPID');

                    $previous = self::$previousMultiTags[$eqpId] ?? array();

                    /** @var Carbon $startedAt, $endedAt */
                    $startedAt = is_null($previous['ended_at'] ?? null) ? null : Carbon::create($previous['ended_at']);
                    $startState = $previous['end_state'] ?? null;
                    $endedAt = $multiTag->getAttribute('EventTime');
                    $endState = $multiTag->getAttribute('TAG_Value');

                    $maintainTime = is_null($startedAt) ? null : $startedAt->diffInMilliseconds($endedAt) / 1000;

                    $previous = array_merge($previous, [
                        'equipment_id' => $eqpId,
                        'type' => MultiTag::query()
                            ->where('TAG_Type', 'Control_State')
                            ->where('EventTime', '<=', $endedAt)
                            ->orderBy('EventTime', 'DESC')
                            ->first()
                            ?->getAttribute('TAG_Value'),
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

                    if($startedAt != null) {
                        // group by 로 잘 주물러보면은 될것만같은데..?
                        $pause = MultiTag::query()
                            ->where('EventTime', '>', $startedAt->subSeconds(2))
                            ->where('EventTime', '<=', $endedAt)
                            ->whereIn('TAG_Type', ['Auto_Stop', 'Manual_Stop'])
                            ->orderBy('EventTime', 'ASC')
                            ->first();

                        $pauseReason = $pause?->getAttribute('TAG_Value');
                        $pauseType = $pause?->getAttribute('TAG_Type');

                        $pauseRange = MultiTag::query()
                            ->where('EventTime', '>', $startedAt->subSeconds(2))
                            ->where('EventTime', '<=', $endedAt)
                            ->where('TAG_Type', 'Down_MCC')
                            ->orderBy('EventTime')
                            ->first();
                        $pauseInterval = $pauseRange?->getAttribute('TAG_Value');

                        $alarm = AlarmHistory::query()
                            ->where('EQPID', $eqpId)
                            ->where('EventFlag', 'S')
                            ->where('EventTime', '>', $startedAt->subSeconds(2))
                            ->where('EventTime', '<=', $endedAt)
                            ->orderBy('EventTime', 'ASC')
                            ->first();
                        $alarmStartedAt = $alarm?->getAttribute('EventTime');
                        $alarmCode = $alarm?->getAttribute('AlarmCode');

                        if($alarmStartedAt != null) {
                            $alarmEndedAt = AlarmHistory::query()
                                ->where('EQPID', $eqpId)
                                ->where('EventFlag', 'E')
                                ->where('EventTime', '>=', $alarmStartedAt)
                                ->orderBy('EventTime', 'ASC')
                                ->first()
                                ?->getAttribute('EventTime');
                        }

                        // TODO : $alarmEndedAt 이 null일 경우 스케줄러 작동하기

                        if($alarmStartedAt != null && $alarmEndedAt != null) {
                            $alarmMaintainTime = $alarmStartedAt->diffInMilliseconds($alarmEndedAt) / 1000;
                        }
                    }

                    $previous = array_merge($previous, [
                        'maintain_time' => $maintainTime,
                        'pause_type' => $pauseType,
                        'pause_reason' => $pauseReason,
                        'pause_interval' => $pauseInterval,
                        'alarm_started_at' => $alarmStartedAt,
                        'alarm_ended_at' => $alarmEndedAt,
                        'alarm_code' => $alarmCode,
                        'alarm_maintain_time' => $alarmMaintainTime,
                    ]);

                    self::$insertCache[] = $previous;

                    self::$previousMultiTags[$eqpId] = $previous;

                    // dump(self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000);
                }

                Processed::query()->insert(self::$insertCache);

                self::$insertCache = [];

                dump(self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000);
            });

        foreach(self::$previousMultiTags as $previous) {
            $startedAt = is_null($previous['ended_at'] ?? null) ? null : Carbon::create($previous['ended_at']);
            $startState = $previous['end_state'] ?? null;

            $type = $startedAt == null ? null : MultiTag::query()
                ->where('TAG_Type', 'Control_State')
                ->where('EventTime', '<=', $startedAt)
                ->orderBy('EventTime', 'DESC')
                ->first()
                ?->getAttribute('TAG_Value');

            $previous = [
                'equipment_id' => $previous['equipment_id'],
                'type' => $type,
                'started_at' => $startedAt,
                'start_state' => $startState,
                'ended_at' => null,
                'end_state' => null,
            ];

            Processed::create($previous);
        }

        $totalEndedAt = Carbon::now();
    }
}
