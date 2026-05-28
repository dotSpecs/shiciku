<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasAuthorName = Schema::hasColumn('mingjus', 'author_name');
        $hasChaodai = Schema::hasColumn('mingjus', 'chaodai');

        if (!$hasAuthorName || !$hasChaodai) {
            Schema::table('mingjus', function (Blueprint $table) use ($hasAuthorName, $hasChaodai) {
                if (!$hasAuthorName) {
                    $table->string('author_name', 128)->nullable()->after('author_id');
                }
                if (!$hasChaodai) {
                    $table->string('chaodai', 32)->nullable()->after('dynasty_id');
                }
            });
        }
    }

    public function down(): void
    {
        $hasAuthorName = Schema::hasColumn('mingjus', 'author_name');
        $hasChaodai = Schema::hasColumn('mingjus', 'chaodai');

        if ($hasAuthorName || $hasChaodai) {
            Schema::table('mingjus', function (Blueprint $table) use ($hasAuthorName, $hasChaodai) {
                if ($hasChaodai) {
                    $table->dropColumn('chaodai');
                }
                if ($hasAuthorName) {
                    $table->dropColumn('author_name');
                }
            });
        }
    }
};
