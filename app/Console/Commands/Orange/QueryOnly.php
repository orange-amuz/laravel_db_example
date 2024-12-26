<?php

namespace App\Console\Commands\Orange;

use App\Models\AlarmHistory;
use App\Models\MultiTag;
use App\Models\Processed;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class QueryOnly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orange:query-only';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Orange's query only result";

    public static array $previousMultiTags;

    public static array $insertCache = [];

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

        self::$previousMultiTags = array();
        self::$totalCount = MultiTag::query()->where('TAG_Type', 'Equipment_State')->count();
        self::$currentCount = 0;
        self::$totalStartedAt = Carbon::now();

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

                    $maintainTime = is_null($startedAt) ? null : abs($startedAt->diffInMilliseconds($endedAt) / 1000);

                    $type = MultiTag::query()
                        ->where('EQPID', $eqpId)
                        ->where('TAG_Type', 'Control_State')
                        ->where('EventTime', '<=', $endedAt)
                        ->orderBy('EventTime', 'DESC')
                        ->first()
                        ?->getAttribute('TAG_Value');

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
                        $pause = MultiTag::query()
                            ->where('EQPID', $eqpId)
                            ->where('EventTime', '>', $startedAt->subSeconds(2))
                            ->where('EventTime', '<=', $endedAt)
                            ->whereIn('TAG_Type', ['Auto_Stop', 'Manual_Stop'])
                            ->orderBy('EventTime', 'ASC')
                            ->first();

                        $pauseReason = $pause?->getAttribute('TAG_Value');
                        $pauseType = $pause?->getAttribute('TAG_Type');

                        $pauseRange = MultiTag::query()
                            ->where('EQPID', $eqpId)
                            ->where('EventTime', '>', $startedAt->subSeconds(2))
                            ->where('EventTime', '<=', $endedAt)
                            ->where('TAG_Type', 'Down_MCC')
                            ->orderBy('EventTime', 'ASC')
                            ->first();

                        $pauseInterval = $pauseRange?->getAttribute('TAG_Value');

                        $alarm = AlarmHistory::query()
                            ->where('EQPID', $eqpId)
                            ->where('EventTime', '>', $startedAt->subSeconds(2))
                            ->where('EventTime', '<=', $endedAt)
                            ->where('EventFlag', 'S')
                            ->orderBy('EventTime', 'ASC')
                            ->first();
                        $alarmStartedAt = $alarm?->getAttribute('EventTime');
                        $alarmCode = $alarm?->getAttribute('AlarmCode');

                        if($alarmStartedAt != null) {
                            $alarmEndedAt = AlarmHistory::query()
                                ->where('EQPID', $eqpId)
                                ->where('AlarmCode', $alarmCode)
                                ->where('EventTime', '>=', $alarmStartedAt)
                                ->where('EventFlag', 'E')
                                ->orderBy('EventTime', 'ASC')
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

        // 원래의 조건대로라면 아래의 구문이 있어야하나, 장기적으로 이 커맨드를 돌릴 경우
        // 데이터에 혼동이 생길것으로 예상되어, 저는 실행하지 않는 쪽으로 결정내렸습니다.
        // self::$insertCache = [];

        // foreach(self::$previousMultiTags as $previous) {
        //     $startedAt = is_null($previous['ended_at'] ?? null) ? null : Carbon::create($previous['ended_at']);
        //     $startState = $previous['end_state'] ?? null;

        //     $type = $startedAt == null ? null : MultiTag::query()
                // ->where('EQPID', $eqpId)
                // ->where('TAG_Type', 'Control_State')
                // ->where('EventTime', '<=', $startedAt)
                // ->orderBy('EventTime', 'DESC')
                // ->first()
                // ?->getAttribute('TAG_Value');

        //     $previous = [
        //         'equipment_id' => $previous['equipment_id'],
        //         'type' => $type,
        //         'started_at' => $startedAt,
        //         'start_state' => $startState,
        //         'ended_at' => null,
        //         'end_state' => null,
        //         'maintain_time' => null,
        //         'pause_type' => null,
        //         'pause_reason' => null,
        //         'pause_interval' => null,
        //         'alarm_started_at' => null,
        //         'alarm_ended_at' => null,
        //         'alarm_code' => null,
        //         'alarm_maintain_time' => null,
        //     ];

        //     self::$insertCache[] = $previous;
        // }

        // Processed::query()->insert(self::$insertCache);

        $totalEndedAt = Carbon::now();

        dump('total time : ' . abs(self::$totalStartedAt->diffInMilliseconds($totalEndedAt) / 1000));
    }
}
