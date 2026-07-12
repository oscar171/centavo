<?php

namespace App\Models\Scopes;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Constrains a model that belongs to the user through an `account_id` (accounts
 * are the only user-owned root). The check runs against the raw accounts table
 * so it does not depend on — nor recurse into — the Account model scope. Only
 * applies inside an authenticated context; jobs, seeders and the console (which
 * have no authenticated user) are unaffected.
 *
 * @implements Scope<Model>
 */
class BelongsToUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::hasUser()) {
            return;
        }

        $builder->whereIn($model->qualifyColumn('account_id'), function (QueryBuilder $query): void {
            $query->select('id')
                ->from('accounts')
                ->where('user_id', Auth::id());
        });
    }
}
