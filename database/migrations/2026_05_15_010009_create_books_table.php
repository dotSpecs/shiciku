<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('id_str', 32)->unique();
            $table->string('id_check', 64)->nullable();
            $table->string('book_id', 16)->nullable()->unique();
            $table->string('name', 512);
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->unsignedBigInteger('dynasty_id')->nullable()->index();
            $table->longText('content')->nullable();
            $table->string('author_name', 128)->nullable();
            $table->string('chaodai', 32)->nullable();
            $table->string('bieming', 512)->nullable();
            $table->string('fenlei', 512)->nullable();
            $table->string('class', 50)->nullable()->index();
            $table->string('type', 50)->nullable();
            $table->unsignedInteger('mingju_num')->default(0);
            $table->string('big_pic_url', 255)->nullable();
            $table->string('banner_pic_url', 255)->nullable();
            $table->string('langsong_url', 255)->nullable();
            $table->string('zimu_api', 255)->nullable();
            $table->string('up_time', 32)->nullable();
            $table->unsignedBigInteger('up_time_span')->nullable();
            $table->unsignedInteger('order')->default(999999)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
