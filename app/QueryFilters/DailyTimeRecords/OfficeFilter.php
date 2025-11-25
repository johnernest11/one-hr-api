<?php

namespace App\QueryFilters\DailyTimeRecords;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class OfficeFilter extends Filter
{
    private const FILTER_NAME = 'office';

    protected function getFilterName(): string
    {
        return static::FILTER_NAME;
    }

    protected function applyFilter(Builder $builder): Builder
    {
        $filterName = $this->getFilterName();
        $office = request($filterName);

        if (! $office) {
            return $builder;
        }

        return $builder->where('employees.office_id', $office);
    }
}
