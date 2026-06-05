<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dictation_attempt_items', function (Blueprint $table) {
            $table->text('accepted_answers')->nullable()->change();
        });

        Schema::table('dictation_wrong_items', function (Blueprint $table) {
            $table->text('accepted_answers')->nullable()->change();
        });

        $this->rewriteAcceptedAnswers('dictation_attempt_items');
        $this->rewriteAcceptedAnswers('dictation_wrong_items');
    }

    public function down(): void
    {
        Schema::table('dictation_attempt_items', function (Blueprint $table) {
            $table->json('accepted_answers')->nullable()->change();
        });

        Schema::table('dictation_wrong_items', function (Blueprint $table) {
            $table->json('accepted_answers')->nullable()->change();
        });
    }

    private function rewriteAcceptedAnswers(string $table): void
    {
        DB::table($table)
            ->select('id', 'accepted_answers')
            ->whereNotNull('accepted_answers')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->accepted_answers, true);
                    if (! is_array($decoded)) {
                        continue;
                    }

                    $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($json === false) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['accepted_answers' => $json]);
                }
            });
    }
};
