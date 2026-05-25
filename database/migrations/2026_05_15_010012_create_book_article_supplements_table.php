<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_article_supplements', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('article_id')->index();
            $table->enum('category', ['fanyi', 'zhushi', 'duanshang', 'shangxi'])->index();
            $table->string('name', 100)->nullable();
            $table->string('author', 100)->nullable();
            $table->longText('content')->nullable();
            $table->text('cankao')->nullable();
            $table->boolean('is_duanyi')->default(false);
            $table->string('langsong_url', 255)->nullable();
            $table->string('zimu_api', 255)->nullable();
            $table->string('up_time', 32)->nullable();
            $table->unsignedBigInteger('up_time_span')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_article_supplements');
    }
};
