<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zhuanti_chapters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zhuanti_id')->index();
            $table->string('name', 100)->nullable();
            $table->string('sub_title', 100)->nullable();
            $table->string('nav', 50)->nullable();
            $table->string('sub_nav', 50)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zhuanti_chapters');
    }
};
