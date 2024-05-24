<?php

namespace App\Traits\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait CanResolveModelFromId
{
    /**
     * Get a new Eloquent instance from Model or ID
     */
    public function retrieveModel(Model|int|string $modelOrId, Builder $query): Model
    {
        $record = $modelOrId;
        if (! ($record instanceof Model)) {
            // The implementing class must have a `model` property
            $record = $query->findOrFail($modelOrId);
        }

        return $record;
    }
}
