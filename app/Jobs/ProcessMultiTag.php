<?php

namespace App\Jobs;

use Database\Seeders\MultiTagIndexedSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMultiTag implements ShouldQueue
{
    use Queueable;

    public $id = 'multiTag';

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
        (new MultiTagIndexedSeeder)->run(null);
    }
}
