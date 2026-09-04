<?php

namespace App\QueryFilters\Item;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class WithEmployeeName
{
    public function handle(Builder $builder, Closure $next): Builder
    {
        $builder->select('items.*')
            ->selectRaw("
                TRIM(CONCAT_WS(' ', 
                    NULLIF(individual_basic_details.first_name, ''),
                    NULLIF(individual_basic_details.last_name, ''),
                    NULLIF(individual_basic_details.ext_name, '')
                )) as employee_name
            ")
            ->leftJoin('employees', 'employees.item_id', '=', 'items.id')
            ->leftJoin('individual_basic_details', 'employees.individual_basic_detail_id', '=', 'individual_basic_details.id');

        return $next($builder);
    }
}
