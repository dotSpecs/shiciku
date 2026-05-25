<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('name')->nullable()->change();
            $t->string('email')->nullable()->change();
            $t->string('password')->nullable()->change();
            $t->string('avatar', 255)->nullable()->after('name');
            $t->string('phone', 32)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['phone']);
            $t->dropColumn(['avatar', 'phone']);
        });
    }
};
