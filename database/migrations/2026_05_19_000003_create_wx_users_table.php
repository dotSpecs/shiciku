<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wx_users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('appid', 64);
            $t->string('openid', 64);
            $t->string('unionid', 64)->nullable()->index();
            $t->timestamps();
            $t->unique(['appid', 'openid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wx_users');
    }
};
