<?php

namespace App\QueryFilters\Item;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class EmploymentStatusFilter extends Filter
{
    public const FILTER_NAME = 'employment-status';

    /**
     * {@inheritDoc}
     */
    protected function applyFilter(Builder $builder): Builder
    {
        $filterName = $this->getFilterName();
        $employment_status = strtolower(request($filterName));

        return $builder->where('employment_status', $employment_status);
    }

    /**
     * {@inheritDoc}
     */
    protected function getFilterName(): string
    {
        return static::FILTER_NAME;
    }
}
