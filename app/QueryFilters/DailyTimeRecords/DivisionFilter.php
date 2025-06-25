<?php

namespace App\QueryFilters\DailyTimeRecords;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DivisionFilter extends Filter
{
    private const FILTER_NAME = 'division';

    /**
     * {@inheritDoc}
     */
    protected function getFilterName(): string
    {
        return static::FILTER_NAME;
    }

    /**
     * {@inheritDoc}
     */
    protected function applyFilter(Builder $builder): Builder
    {
        $filterName = $this->getFilterName();
        $division = strtolower(request($filterName));

        if (! $division) {
            return $builder;
        }

        return $builder->where('employees.division_id', $division);
    }
}
