<?php

namespace App\QueryFilters\DailyTimeRecords;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DateFilter extends Filter
{
    private const FILTER_NAME = 'date';

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

        $date = strtolower(request($filterName));

        if (! $date) {
            return $builder;
        }

        return $builder->where('daily_time_records.date', $date);
    }
}
