<?php

namespace App\QueryFilters\LocatorSlip;

use App\QueryFilters\Filter;
use Illuminate\Database\Eloquent\Builder;

class FormTypeFilter extends Filter
{
    public const FILTER_NAME = 'form-type';

    /**
     * {@inheritDoc}
     */
    protected function applyFilter(Builder $builder): Builder
    {
        $filterName = $this->getFilterName();
        $formType = strtolower(request($filterName));

        if ($builder->getModel()->getTable() === 'locator_slips') {
            return $builder->where('form_type', $formType);
        }

        return $builder->whereHas('locatorSlip', fn ($slip) => $slip->where('form_type', $formType));
    }

    /**
     * {@inheritDoc}
     */
    protected function getFilterName(): string
    {
        return static::FILTER_NAME;
    }
}
