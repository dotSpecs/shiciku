<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poems', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('id_str', 32)->unique();
            $table->string('id_check', 64)->nullable();
            $table->string('poem_id', 16)->nullable()->unique();
            $table->string('name', 512);
            $table->string('name_py', 1024)->nullable();
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->string('author_name', 128)->nullable();
            $table->unsignedBigInteger('dynasty_id')->nullable()->index();
            $table->string('chaodai', 32)->nullable();
            $table->longText('content')->nullable();
            $table->longText('content_py')->nullable();
            $table->string('author_py', 255)->nullable();
            $table->string('chaodai_py', 64)->nullable();
            $table->string('type', 16)->nullable()->index();
            $table->string('bieming', 255)->nullable();
            $table->string('yzsy', 32)->nullable();
            $table->string('langsong_author', 64)->nullable();
            $table->string('langsong_url', 255)->nullable();
            $table->string('zimu_api', 255)->nullable();
            $table->longText('yizhu_content')->nullable();
            $table->string('yizhu_author', 100)->nullable();
            $table->text('yizhu_cankao')->nullable();
            $table->string('up_time', 32)->nullable();
            $table->unsignedBigInteger('up_time_span')->nullable();
            $table->unsignedInteger('order')->default(999999)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poems');
    }
};
