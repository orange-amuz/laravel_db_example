<?php

namespace App\Console\Commands\Orange;

use App\Models\AlarmHistory;
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

class WithCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orange:with-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Orange's with cache result";

    public static array $previousMultiTags;

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

        self::$previousMultiTags = array();
        self::$totalCount = MultiTag::query()->where('TAG_Type', 'Equipment_State')->count();
        self::$currentCount = 0;
        self::$totalStartedAt = Carbon::now();

        // 1. 정합
        // 2. 속도

        // 본격적인 가공된 데이터 생성
        MultiTag::query()
            ->where('TAG_Type', 'Equipment_State')
            ->orderBy('EventTime', 'ASC')
            ->chunk(1000, function(Collection $equipmentStates) {
                $firstAt = Carbon::create($equipmentStates->first()->getAttribute('EventTime'));
                $lastAt = Carbon::create($equipmentStates->last()->getAttribute('EventTime'));

                $controlStates = MultiTag::query()
                    ->where('TAG_Type', 'Control_State')
                    ->where('EventTime', '>', $firstAt)
                    ->where('EventTime', '<=' , $lastAt)
                    ->orderBy('EventTime', 'DESC')
                    ->get()
                    ->groupBy('EQPID');
                dump('control states done at : ' . abs(self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000));

                $pauses = MultiTag::query()
                    ->whereIn('TAG_Type', ['Auto_Stop', 'Manual_Stop'])
                    ->where('EventTime', '>', $firstAt->subSeconds(2))
                    ->where('EventTime', '<=', $lastAt)
                    ->orderBy('EventTime', 'ASC')
                    ->get()
                    ->groupBy('EQPID');
                dump('pauses done at : ' . abs(self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000));

                $pauseRanges = MultiTag::query()
                    ->where('EventTime', '>', $firstAt->subSeconds(2))
                    ->where('EventTime', '<=', $lastAt)
                    ->orderBy('EventTime', 'ASC')
                    ->get()
                    ->groupBy('EQPID');
                dump('pause range done at : ' . abs(self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000));

                $alarms = AlarmHistory::query()
                    ->where('EventTime', '>', $firstAt->subSeconds(2))
                    ->orderBy('EventTime', 'ASC')
                    ->get()
                    ->groupBy('EQPID');
                dump('alarms done at : ' . abs(self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000));

                foreach($equipmentStates as $equipmentState) {
                    $eqpId = $equipmentState->getAttribute('EQPID');

                    $previous = self::$previousMultiTags[$eqpId] ?? [];

                    $startedAt = is_null($previous['ended_at'] ?? null) ? null : Carbon::create($previous['ended_at']);
                    $startState = $previous['end_state'] ?? null;
                    $endedAt = $equipmentState->getAttribute('EventTime');
                    $endState = $equipmentState->getAttribute('TAG_Value');

                    $maintainTime = is_null($startedAt) ? null : abs($startedAt->diffInMilliseconds($endedAt) / 1000);


                    // 이진 탐색은 시간복잡도 logN을 가집니다.
                    $type = $this
                        ->findFirstSmall($controlStates->get($eqpId)?->toArray() ?? [], $endedAt)
                        ?->get('TAG_Value');

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
                        $pause = null;
                        $pauseTemp = $this
                            ->findFirstLarge($pauses->get($eqpId)?->toArray() ?? [], $startedAt);

                        if($pauseTemp != null) {
                            if(Carbon::create($pauseTemp->get('EventTime'))->lte($endedAt)) {
                                $pause = $pauseTemp;
                            }
                        }

                        $pauseReason = $pause?->get('TAG_Value');
                        $pauseType = $pause?->get('TAG_Type');

                        $pauseRange = null;
                        $pauseRangeTemp = $this
                            ->findFirstLarge($pauseRanges->get($eqpId)?->toArray() ?? [], $startedAt);

                        if($pauseRangeTemp != null) {
                            if(Carbon::create($pauseRangeTemp->get('EventTime'))->lte($endedAt)) {
                                $pauseRange = $pauseRangeTemp;
                            }
                        }

                        $pauseInterval = $pauseRange?->get('TAG_Value');

                        $alarmGrouped = $alarms->get($eqpId)?->groupBy('EventFlag');

                        $alarm = null;
                        $alarmTemp = $this
                            ->findFirstLarge($alarmGrouped?->get('S')?->values()->toArray() ?? [], $startedAt);

                        if($alarmTemp != null) {
                            if(Carbon::create($alarmTemp->get('EventTime'))->lte($endedAt)) {
                                $alarm = $alarmTemp;
                            }
                        }

                        $alarmStartedAt = $alarm?->get('EventTime');
                        $alarmCode = $alarm?->get('AlarmCode');

                        if($alarmStartedAt != null) {
                            $alarmEndedAt = $this
                                ->findFirstLarge($alarmGrouped?->get('E')->where('AlarmCode', $alarmCode)?->values()->toArray() ?? [], Carbon::create($alarmStartedAt))
                                ?->get('EventTime');
                        }

                        // // TODO : $alarmEndedAt 이 null일 경우 스케줄러 작동하기

                        if($alarmStartedAt != null && $alarmEndedAt != null) {
                            $alarmMaintainTime = abs(Carbon::create($alarmStartedAt)->diffInMilliseconds(Carbon::create($alarmEndedAt)) / 1000);
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

                    dump([
                        self::$totalStartedAt->diffInMilliseconds(Carbon::now()) / 1000,
                        ++self::$currentCount . '/' . self::$totalCount,
                    ]);
                }

                Processed::query()->insert(self::$insertCache);

                self::$insertCache = array();
            });

        $totalEndedAt = Carbon::now();

        dump('total time : ' . abs(self::$totalStartedAt->diffInMilliseconds($totalEndedAt) / 1000));
    }

    private function findFirstSmall(array $array, Carbon $target) : Collection|null {
        $leftIndex = 0;
        $rightIndex = count($array) - 1;

        $result = null;

        while($leftIndex <= $rightIndex) {
            $midIndex = intdiv($leftIndex + $rightIndex, 2);
            $mid = Carbon::create($array[$midIndex]['EventTime']);

            if($mid->lte($target)) {
                $result = $array[$midIndex];

                $leftIndex = $midIndex + 1;
            }
            else {
                $rightIndex = $midIndex - 1;
            }
        }

        if($result != null) {
            return Collection::make($result);
        }

        return $result;
    }

    private function findFirstLarge(array $array, Carbon $target) : Collection|null {
        $leftIndex = 0;
        $rightIndex = count($array) - 1;

        $result = null;

        while($leftIndex <= $rightIndex) {
            $midIndex = intdiv($leftIndex + $rightIndex, 2);
            $mid = Carbon::create($array[$midIndex]['EventTime']);

            if($mid->gte($target)) {
                $result = $array[$midIndex];

                $rightIndex = $midIndex - 1;
            }
            else {
                $leftIndex = $midIndex + 1;
            }
        }

        if($result != null) {
            return Collection::make($result);
        }

        return $result;
    }
}
