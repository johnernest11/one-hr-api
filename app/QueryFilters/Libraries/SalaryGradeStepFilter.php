<?php

namespace App\QueryFilters\Libraries;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class SalaryGradeStepFilter extends Filter
{
    private const FILTER_NAME = 'step';

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
        $sgStep = strtolower(request($filterName));

        return $builder->where('step', $sgStep);
    }
}
