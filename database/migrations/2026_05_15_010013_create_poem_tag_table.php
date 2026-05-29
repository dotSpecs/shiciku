<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poem_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poem_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->unsignedInteger('order')->default(999999)->index();
            $table->unsignedTinyInteger('show')->default(0)->comment('是否展示: 0=否, 1=是');
            $table->unique(['poem_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poem_tag');
    }
};
