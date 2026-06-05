<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictation_attempt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attempt_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('poem_id')->index();
            $table->unsignedBigInteger('zhuanti_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->string('question_type', 32);
            $table->text('prompt');
            $table->text('answer');
            $table->text('accepted_answers')->nullable();
            $table->text('user_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['attempt_id', 'sort'], 'idx_dict_attempt_item_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictation_attempt_items');
    }
};
