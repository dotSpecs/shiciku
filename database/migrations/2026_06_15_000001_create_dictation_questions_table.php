<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictation_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poem_id')->index();
            $table->unsignedBigInteger('zhuanti_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->string('grade_name', 64);
            $table->string('question_type', 32);
            $table->text('prompt');
            $table->text('answer')->nullable();
            $table->text('accepted_answers')->nullable();
            $table->text('options')->nullable();
            $table->text('metadata')->nullable();
            $table->string('source_key', 191)->unique('uniq_dict_question_source');
            $table->char('source_hash', 40);
            $table->unsignedTinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();

            $table->index(['grade_name', 'question_type', 'status'], 'idx_dict_question_grade_type');
            $table->index(['poem_id', 'question_type'], 'idx_dict_question_poem_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictation_questions');
    }
};
