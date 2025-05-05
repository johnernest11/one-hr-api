<?php

namespace App\QueryFilters\Libraries;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters Salary Grades by Implementation Effectivity Date
 */
class SalaryGradeEffectiveDateFilter extends Filter
{
    private const FILTER_NAME = 'effective-date';

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
        $sgDate = strtolower(request($filterName));

        return $builder->where('effective_date', 'like', "$sgDate%"); // Use like so that we can filter based on year only as well.
    }
}
