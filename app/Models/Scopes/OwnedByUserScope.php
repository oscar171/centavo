<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Constrains a model that has a direct `user_id` column to the authenticated
 * user. Only applies inside an authenticated context (HTTP requests); queue
 * jobs, seeders and console commands run without a user and are unaffected.
 *
 * @implements Scope<Model>
 */
class OwnedByUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::hasUser()) {
            return;
        }

        $builder->where($model->qualifyColumn('user_id'), Auth::id());
    }
}
