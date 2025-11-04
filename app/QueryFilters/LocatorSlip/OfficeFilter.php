<?php

namespace App\QueryFilters\LocatorSlip;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class OfficeFilter extends Filter
{
    private const FILTER_NAME = 'office';

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
        $office = strtolower(request($filterName));

        if (! $office) {
            return $builder;
        }

        return $builder->where('employees.office_id', $office);
    }
}
