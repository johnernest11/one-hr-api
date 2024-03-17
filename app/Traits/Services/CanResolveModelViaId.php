<?php

namespace App\Traits\Services;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Model;

trait CanResolveModelViaId
{
    /**
     * Get a new Eloquent instance from Model or ID
     */
    public function retrieveModel(Model|int|string $modelOrId): Model
    {
        if (! property_exists($this, 'model')) {
            $message = 'Cannot call '.__METHOD__.' on '.get_class($this).' without the `model` property';
            throw new BadMethodCallException($message);
        }

        $record = $modelOrId;
        if (! ($record instanceof Model)) {
            // The implementing class must have a `model` property
            $record = $this->model::findOrFail($modelOrId);
        }

        return $record;
    }
}
