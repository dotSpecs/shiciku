<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('book_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->primary(['book_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_tag');
    }
};
