<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('id_str', 32)->unique();
            $table->string('id_check', 64)->nullable();
            $table->string('author_id', 16)->nullable()->unique();
            $table->string('name', 255);
            $table->unsignedBigInteger('dynasty_id')->nullable()->index();
            $table->longText('content')->nullable();
            $table->string('pic', 512)->nullable();
            $table->string('pic_small', 255)->nullable();
            $table->string('pic_big', 255)->nullable();
            $table->unsignedInteger('shiwen_num')->default(0);
            $table->unsignedInteger('mingju_num')->default(0);
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
        Schema::dropIfExists('authors');
    }
};
