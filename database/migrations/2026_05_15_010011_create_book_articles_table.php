<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('id_str', 32)->unique();
            $table->string('article_id', 16)->nullable()->unique();
            $table->unsignedBigInteger('book_id')->index();
            $table->unsignedBigInteger('chapter_id')->index();
            $table->unsignedInteger('num')->nullable();
            $table->string('name', 512);
            $table->string('author', 100)->nullable();
            $table->longText('content')->nullable();
            $table->string('fenlei', 100)->nullable();
            $table->boolean('yiyi')->default(false);
            $table->string('langsong_url', 255)->nullable();
            $table->string('zimu_api', 255)->nullable();
            $table->string('up_time', 32)->nullable();
            $table->unsignedBigInteger('up_time_span')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_articles');
    }
};
