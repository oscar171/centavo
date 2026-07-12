<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Assigns an unguessable public UUID on creation and routes the model by it,
 * so sequential primary keys are never exposed in URLs.
 */
trait HasUuid
{
    /**
     * Boot the trait: generate a UUID when one has not been set already.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getAttribute('uuid')) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }

    /**
     * Route model binding resolves this model by its UUID.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
