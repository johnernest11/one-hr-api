<?php

namespace App\QueryFilters\Libraries;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class SalaryGradeFilter extends Filter
{
    private const FILTER_NAME = 'sg';

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
        $sg = strtolower(request($filterName));

        return $builder->where('salary_grade', $sg);
    }
}
