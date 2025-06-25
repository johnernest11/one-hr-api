<?php

namespace App\QueryFilters\DailyTimeRecords;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class TimeInOrOutFilter extends Filter
{
    private const FILTER_NAME = 'log-type';

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
        $logType = strtolower(request($filterName));

        if (! $logType) {
            return $builder;
        }

        $isIn = $logType == 'in' ? true : false;

        return $builder->where('time_logs.is_in', $isIn);
    }
}
