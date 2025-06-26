<?php

namespace App\QueryFilters\DailyTimeRecords;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class SectionFilter extends Filter
{
    private const FILTER_NAME = 'section';

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
        $section = strtolower(request($filterName));

        if (! $section) {
            return $builder;
        }

        return $builder->where('employees.section_or_unit_id', $section);
    }
}
