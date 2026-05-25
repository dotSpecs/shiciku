<?php

namespace App\Services\Guwendao;

use App\Models\Dynasty;

class DynastyResolver
{
    public function for(?string $name): ?Dynasty
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        return Dynasty::firstOrCreate(['name' => $name]);
    }
}
