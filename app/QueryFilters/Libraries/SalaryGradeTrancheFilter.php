<?php

namespace App\QueryFilters\Libraries;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class SalaryGradeTrancheFilter extends Filter
{
    private const FILTER_NAME = 'tranche';

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
        $sgTranche = strtolower(request($filterName));

        return $builder->where('tranche', $sgTranche);
    }
}
