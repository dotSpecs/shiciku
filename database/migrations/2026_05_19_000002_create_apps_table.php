<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $t) {
            $t->id();
            $t->string('app_key', 64)->unique();
            $t->string('appid', 64)->unique();
            $t->string('secret', 64);
            $t->string('name', 64)->nullable();
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
