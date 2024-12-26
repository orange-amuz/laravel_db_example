<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    static int $total = 0;
    static int $current = 0;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('alarm_history_indexeds');

        Schema::create('alarm_history_indexeds', function (Blueprint $table) {
            $table->id();

            $table->string('equipment_id')->comment('설비 ID')->index();

            $table->timestamp('event_time', precision: 3)->comment('발생 시간')->index();

            $table->string('event_flag')->comment('이벤트 Flag')->index();

            $table->string('alarm_code')->comment('알람 코드')->index();

            $table->timestamps();

            // 검색 최적화를 위한 인덱스 추가
            $table->index(['equipment_id', 'alarm_code', 'event_flag', 'event_time'], 'alarm_history_indexeds_total_index');
            $table->index(['equipment_id', 'event_flag', 'event_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarm_history_indexed');
    }
};
