<?php

namespace App\Models\LocatorSlip;

use App\Enums\DocumentStatus;
use App\Enums\LocatorFormType;
use App\Enums\Period;
use App\Models\ComprehensiveRecords\Employee;
use App\QueryFilters\LocatorSlip\FormTypeFilter;
use App\QueryFilters\LocatorSlip\LocatorMonthFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pipeline\Pipeline;

class LocatorSlip extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'locator_slip_no',
        'date',
        'period',
        'status',
        'form_type',
    ];

    /**
     * The attributes that should be eager-loaded
     *
     * @var array<int, string>
     */
    protected $with = [
        'employee',
        'locatorSlipLogger',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'status' => DocumentStatus::class,
        'period' => Period::class,
        'form_type' => LocatorFormType::class,
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
                FormTypeFilter::class,
                LocatorMonthFilter::class,
            ])
            ->thenReturn();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function locatorSlipLogger(): HasMany
    {
        return $this->hasMany(LocatorSlipLogger::class);
    }
}
