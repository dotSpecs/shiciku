<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedBigInteger('zhuanti_id')->nullable()->unique()->after('id');
        });

        foreach (DB::table('zhuantis')->select('id', 'name')->get() as $z) {
            DB::table('tags')->where('name', $z->name)->update(['zhuanti_id' => $z->id]);
        }
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['zhuanti_id']);
            $table->dropColumn('zhuanti_id');
        });
    }
};
