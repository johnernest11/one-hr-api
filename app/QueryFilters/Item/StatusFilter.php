<?php

namespace App\QueryFilters\Item;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class StatusFilter extends Filter
{
    public const FILTER_NAME = 'status';

    /**
     * {@inheritDoc}
     */
    protected function applyFilter(Builder $builder): Builder
    {
        $filterName = $this->getFilterName();
        $status = strtolower(request($filterName));

        return $builder->where('status', $status);
    }

    /**
     * {@inheritDoc}
     */
    protected function getFilterName(): string
    {
        return static::FILTER_NAME;
    }
}
