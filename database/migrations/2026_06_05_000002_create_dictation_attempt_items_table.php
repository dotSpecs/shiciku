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
            $table->unsignedBigInteger('question_id');
            $table->text('user_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['attempt_id', 'sort'], 'idx_dict_attempt_item_sort');
            $table->index('question_id', 'idx_dict_attempt_item_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictation_attempt_items');
    }
};
