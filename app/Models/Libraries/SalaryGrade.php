<?php

namespace App\Models\Libraries;

use App\QueryFilters\Generic\ActiveFilter;
use App\QueryFilters\Libraries\SalaryGradeEffectiveDateFilter;
use App\QueryFilters\Libraries\SalaryGradeFilter;
use App\QueryFilters\Libraries\SalaryGradeNBCNoFilter;
use App\QueryFilters\Libraries\SalaryGradeStepFilter;
use App\QueryFilters\Libraries\SalaryGradeTrancheFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;

class SalaryGrade extends Model
{
    use HasFactory;

    /**
     * The properties that are mass-assignable
     *
     * @var string[]
     */
    protected $fillable = [
        'id',
        'nbc_no',
        'effective_date',
        'tranche',
        'salary_grade',
        'step',
        'amount',
        'active',
    ];

    /**
     * @Scope
     * Pipeline for HTTP query filters
     */
    public function scopeFiltered(Builder $builder): Builder
    {
        return app(Pipeline::class)
            ->send($builder)
            ->through([
                SalaryGradeNBCNoFilter::class,
                SalaryGradeEffectiveDateFilter::class,
                SalaryGradeTrancheFilter::class,
                SalaryGradeFilter::class,
                SalaryGradeStepFilter::class,
                ActiveFilter::class,
            ])
            ->thenReturn();
    }
}
