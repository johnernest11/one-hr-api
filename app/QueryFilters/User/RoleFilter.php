<?php

namespace App\QueryFilters\User;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RoleFilter extends Filter
{
    public const FILTER_NAME = 'role';

    protected function applyFilter(Builder $builder): Builder
    {
        $role = request($this->getFilterName());

        if (! $role) {
            return $builder;
        }

        // Fetch model_ids from model_has_roles table on the default DB connection
        $roleUserIds = DB::connection('mysql') // change 'default' to your roles DB connection name
            ->table('model_has_roles')
            ->where('role_id', $role)
            ->where('model_type', \App\Models\User::class) // polymorphic check if needed
            ->pluck('model_id');

        return $builder->whereIn('id', $roleUserIds);
    }

    protected function getFilterName(): string
    {
        return static::FILTER_NAME;
    }
}
