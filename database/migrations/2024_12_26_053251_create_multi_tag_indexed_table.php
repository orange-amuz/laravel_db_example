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
        Schema::dropIfExists('multi_tag_indexeds');

        Schema::create('multi_tag_indexeds', function (Blueprint $table) {
            $table->id();

            $table->string('equipment_id')->index();

            $table->timestamp('event_time', precision: 3)->index();

            $table->string('tag_type')->index();

            $table->decimal('tag_value')->nullable();

            $table->string('var_code')->nullable();

            $table->timestamps();

            // 검색 최적화를 위해 인덱스 추가
            $table->index(['equipment_id', 'tag_type', 'event_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multi_tag_indexeds');
    }
};
