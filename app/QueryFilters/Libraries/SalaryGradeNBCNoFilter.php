<?php

namespace App\QueryFilters\Libraries;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters Salary Grades by National Budget Circular No.
 */
class SalaryGradeNBCNoFilter extends Filter
{
    private const FILTER_NAME = 'nbc-no';

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
        $sgNBCNo = strtolower(request($filterName));

        return $builder->where('nbc_no', $sgNBCNo);
    }
}
