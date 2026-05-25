<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_ziliaos', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('author_id')->index();
            $table->string('name', 100)->nullable();
            $table->string('author', 100)->nullable();
            $table->longText('content')->nullable();
            $table->text('cankao')->nullable();
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
        Schema::dropIfExists('author_ziliaos');
    }
};
