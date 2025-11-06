<?php

namespace App\Models\ComprehensiveRecords;

use App\Models\DailyTimeRecords\QrCode;
use App\Models\Item;
use App\Models\Libraries\Division;
use App\Models\Libraries\Office;
use App\Models\Libraries\Program;
use App\Models\Libraries\SalaryGrade;
use App\Models\Libraries\SectionOrUnit;
use App\Models\LocatorSlip\LocatorSlip;
use App\QueryFilters\DailyTimeRecords\DivisionFilter;
use App\QueryFilters\DailyTimeRecords\SectionFilter;
use App\QueryFilters\LocatorSlip\FormTypeFilter;
use App\QueryFilters\LocatorSlip\LocatorMonthFilter;
use App\QueryFilters\LocatorSlip\OfficeFilter;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pipeline\Pipeline;

class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'individual_basic_detail_id',
        'id_number',
        'item_id',
        'salary_grade_id',
        'program_id',
        'office_id',
        'division_id',
        'section_or_unit_id',
        'agency_employee_no',
    ];

    protected $with = [
        'item',
        'division',
        'sectionOrUnit',
    ];

    public function scopeWithLocatorSlip($query): Builder
    {
        return $query->has('locatorSlip')->with('locatorSlip');
    }

    /**
     * Filters for the PAS view locator slip.
     * The main table here is employee.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLocatorSlipFiltered(Builder $builder): Builder
    {
        $pipeline = app(Pipeline::class);

        $builder = $pipeline
            ->send($builder->whereNull('employees.deleted_at'))
            ->through([
                DivisionFilter::class,
                SectionFilter::class,
                OfficeFilter::class,
            ])
            ->thenReturn();

        $builder->distinct('employees.id');

        $builder->with([
            'individualBasicDetail',
            'office',
            'division',
            'sectionOrUnit',
            'locatorSlip' => function ($relation) use ($pipeline) {
                $builder = $relation->getQuery();
                $pipeline
                    ->send($builder)
                    ->through([
                        FormTypeFilter::class,
                        LocatorMonthFilter::class,
                    ])
                    ->thenReturn();
            },
        ]);

        return $builder;

    }

    /**
     * An employee belongs to exactly one individualBasicDetail
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }

    /**
     * An employee belongs to exactly one item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * An employee has one salaryGrade
     */
    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    /**
     * An employee has one section or unit
     */
    public function sectionOrUnit(): BelongsTo
    {
        return $this->belongsTo(SectionOrUnit::class);
    }

    /**
     * An employee has one division
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * An employee has one office
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * An employee has one program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * An employee has one QR code
     */
    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class);
    }

    /**
     * An employee has many locator slips
     */
    public function locatorSlip(): HasMany
    {
        return $this->hasMany(LocatorSlip::class);
    }
}
