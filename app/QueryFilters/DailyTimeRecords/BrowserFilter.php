<?php

namespace App\QueryFilters\DailyTimeRecords;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class BrowserFilter extends Filter
{
    private const FILTER_NAME = 'browser-uid';

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
        $browserUid = strtoupper(request($filterName));

        if (! $browserUid) {
            return $builder;
        }

        return $builder->where('time_logs.browser_uid', $browserUid);
    }
}
