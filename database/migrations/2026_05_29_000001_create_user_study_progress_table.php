<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_study_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('zhuanti_id');
            $table->unsignedBigInteger('poem_id');
            $table->string('status', 16)->default('started');
            $table->unsignedInteger('read_count')->default(0);
            $table->dateTime('learned_at')->nullable();
            $table->dateTime('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'zhuanti_id', 'poem_id'], 'study_progress_user_zhuanti_poem_unique');
            $table->index(['user_id', 'zhuanti_id', 'status'], 'study_progress_user_zhuanti_status_index');
            $table->index(['user_id', 'zhuanti_id', 'last_read_at'], 'study_progress_user_zhuanti_last_read_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_study_progress');
    }
};
