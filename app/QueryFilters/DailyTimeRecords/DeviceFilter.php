<?php

namespace App\QueryFilters\DailyTimeRecords;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DeviceFilter extends Filter
{
    private const FILTER_NAME = 'device-id';

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
        $deviceId = strtoupper(request($filterName));

        if (! $deviceId) {
            return $builder;
        }

        return $builder->where('time_logs.device_id', $deviceId);
    }
}
