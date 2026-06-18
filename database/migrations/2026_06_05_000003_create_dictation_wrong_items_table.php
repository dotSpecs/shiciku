<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictation_wrong_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('first_attempt_item_id')->nullable();
            $table->unsignedBigInteger('last_attempt_item_id')->nullable();
            $table->unsignedBigInteger('poem_id');
            $table->string('grade_name', 64);
            $table->unsignedBigInteger('zhuanti_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->string('question_type', 32);
            $table->text('prompt');
            $table->text('answer');
            $table->text('accepted_answers')->nullable();
            $table->text('options')->nullable();
            $table->text('last_user_answer')->nullable();
            $table->text('instance_metadata')->nullable();
            $table->unsignedInteger('wrong_count')->default(1);
            $table->unsignedInteger('reviewed_count')->default(0);
            $table->dateTime('last_wrong_at')->nullable();
            $table->dateTime('last_reviewed_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'question_id'], 'uniq_dict_wrong_user_question');
            $table->index(['user_id', 'resolved_at'], 'idx_dict_wrong_user_resolved');
            $table->index(['user_id', 'grade_name'], 'idx_dict_wrong_user_grade');
            $table->index('question_id', 'idx_dict_wrong_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictation_wrong_items');
    }
};
