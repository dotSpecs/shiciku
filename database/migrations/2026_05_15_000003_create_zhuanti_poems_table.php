<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zhuanti_poems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zhuanti_id')->index();
            $table->unsignedBigInteger('chapter_id')->index();
            $table->unsignedBigInteger('poem_id')->index();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['zhuanti_id', 'poem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zhuanti_poems');
    }
};
