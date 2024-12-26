<?php

namespace App\Jobs;

use Database\Seeders\AlarmHistoryIndexedSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAlarmHistory implements ShouldQueue
{
    use Queueable;

    public $id = 'alarmHistory';

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        (new AlarmHistoryIndexedSeeder)->run(null);
    }
}
