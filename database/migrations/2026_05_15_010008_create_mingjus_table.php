<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mingjus', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('id_str', 32)->unique();
            $table->string('id_check', 64)->nullable();
            $table->string('mingju_id', 16)->nullable()->unique();
            $table->text('name');
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->string('author_name', 128)->nullable();
            $table->unsignedBigInteger('dynasty_id')->nullable()->index();
            $table->string('chaodai', 32)->nullable();
            $table->string('source', 512)->nullable();
            $table->string('source_id_str', 32)->nullable();
            $table->unsignedBigInteger('source_poem_id')->nullable()->index();
            $table->unsignedBigInteger('source_book_article_id')->nullable()->index();
            $table->string('tag', 512)->nullable();
            $table->longText('yiwen')->nullable();
            $table->longText('shangxi')->nullable();
            $table->longText('zhushi')->nullable();
            $table->tinyInteger('guishu')->default(0)->index();
            $table->string('pic_name', 255)->nullable();
            $table->string('pic_author', 255)->nullable();
            $table->string('pic_cangguan', 255)->nullable();
            $table->string('pic_chaodai', 50)->nullable();
            $table->string('pic_url', 512)->nullable();
            $table->string('up_time', 32)->nullable();
            $table->unsignedBigInteger('up_time_span')->nullable();
            $table->unsignedInteger('order')->default(999999)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mingjus');
    }
};
