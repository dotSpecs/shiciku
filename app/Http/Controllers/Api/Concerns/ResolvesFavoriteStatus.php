<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Favorite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ResolvesFavoriteStatus
{
    private function isFavorited(Request $request, Model $model): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        return Favorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', $model->getMorphClass())
            ->where('favoritable_id', $model->getKey())
            ->exists();
    }
}
