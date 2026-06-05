<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictation_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('scope_type', 32)->default('grade');
            $table->string('grade_name', 64);
            $table->json('chapter_ids')->nullable();
            $table->string('mode', 32)->default('mixed');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_dict_attempt_user_created');
            $table->index(['user_id', 'grade_name', 'created_at'], 'idx_dict_attempt_user_grade_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictation_attempts');
    }
};
