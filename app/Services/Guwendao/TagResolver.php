<?php

namespace App\Services\Guwendao;

use App\Models\Tag;
use Illuminate\Support\Collection;

class TagResolver
{
    public function forString(?string $raw): Collection
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return collect();
        }
        $names = collect(preg_split('/[|,;、]+/u', $raw))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique()
            ->values();

        return $names->map(fn ($name) => Tag::firstOrCreate(['name' => $name]));
    }
}
