<?php

namespace App\QueryFilters\LocatorSlip;

use App\QueryFilters\Filter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class LocatorMonthFilter extends Filter
{
    public const FILTER_NAME = 'date-filter';

    /**
     * {@inheritDoc}
     */
    protected function applyFilter(Builder $builder): Builder
    {
        $filterName = $this->getFilterName();
        $monthYear = Carbon::parse(request($filterName));
        $startMonth = $monthYear->copy()->startOfMonth();
        $endMonth = $monthYear->endOfMonth();

        return $builder->whereBetween('date', [$startMonth, $endMonth]);
    }

    /**
     * {@inheritDoc}
     */
    protected function getFilterName(): string
    {
        return static::FILTER_NAME;
    }
}
