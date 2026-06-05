<?php

use App\Services\Dictation\AnswerNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->rewriteAnswerKeys();

        Schema::table('dictation_wrong_items', function (Blueprint $table) {
            $table->char('answer_key', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dictation_wrong_items', function (Blueprint $table) {
            $table->char('answer_key', 64)->change();
        });
    }

    private function rewriteAnswerKeys(): void
    {
        if (! Schema::hasTable('dictation_wrong_items')) {
            return;
        }

        $normalizer = new AnswerNormalizer;

        DB::table('dictation_wrong_items')
            ->select('id', 'answer', 'accepted_answers')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($normalizer) {
                foreach ($rows as $row) {
                    DB::table('dictation_wrong_items')
                        ->where('id', $row->id)
                        ->update([
                            'answer_key' => $normalizer->answerKey(
                                $this->decodeAcceptedAnswers($row->accepted_answers),
                                (string) $row->answer
                            ),
                        ]);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function decodeAcceptedAnswers(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($answer) => is_scalar($answer) ? (string) $answer : '', $decoded),
            fn (string $answer) => $answer !== ''
        ));
    }
};
