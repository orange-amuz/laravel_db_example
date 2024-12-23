<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processeds', function (Blueprint $table) {
            $table->id();

            $table->string('equipment_id')->comment('설비 ID');
            $table->string('type')->comment('자동/수동')->nullable();

            $table->timestamp('started_at', precision: 3)->comment('시작 시간')->nullable();
            $table->integer('start_state')->comment('시작 상태')->nullable();

            $table->timestamp('ended_at', precision: 3)->comment('종료 시간')->nullable();
            $table->integer('end_state')->comment('종료 상태')->nullable();

            $table->float('maintain_time')->comment('상태 유지 시간')->nullable();

            $table->string('pause_type')->comment('정지 유형')->nullable();
            $table->integer('pause_reason')->comment('정지 사유')->nullable();
            $table->integer('pause_interval')->comment('정지 구간')->nullable();

            $table->timestamp('alarm_started_at', precision: 3)->comment('알람 시작')->nullable();
            $table->string('alarm_code')->comment('알람 코드')->nullable();
            $table->timestamp('alarm_ended_at', precision: 3)->comment('알람 종료')->nullable();
            $table->float('alarm_maintain_time')->comment('알람 유지 시간')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed');
    }
};
