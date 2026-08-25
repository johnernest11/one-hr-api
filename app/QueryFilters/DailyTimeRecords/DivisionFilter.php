<?php

namespace App\QueryFilters\DailyTimeRecords;

use App\Models\ComprehensiveRecords\Employee;
use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DivisionFilter extends Filter
{
    private const FILTER_NAME = 'division';

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
        $division = strtolower(request($filterName));

        if (! $division) {
            return $builder;
        }

        // Determine relation path dynamically based on the base model
        // This is because this filter is used by multiple Models
        $relation = $builder->getModel() instanceof Employee ? 'item' : 'employee.item';

        // Updated such that it will now fetch the division_id from items
        // since the division has been moved from employee->division_id to employee->item->division_id
        return $builder->whereHas($relation, function (Builder $query) use ($division) {
            $query->where('division_id', $division);
        });
    }
}
