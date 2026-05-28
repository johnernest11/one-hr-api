<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\ItemStatus;
use App\Models\Libraries\Division;
use App\Models\Libraries\FundSource;
use App\Models\Libraries\Office;
use App\Models\Libraries\Position;
use App\Models\Libraries\Program;
use App\Models\Libraries\SalaryGrade;
use App\Models\Libraries\SectionOrUnit;
use App\QueryFilters\Item\EmploymentStatusFilter;
use App\QueryFilters\Item\StatusFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pipeline\Pipeline;

class Item extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $connection = 'mysql';

    protected $table = 'items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Organization Data
        'division_id',
        'section_or_unit_id',
        'program_id',
        'office_id',
        'psipop_id',

        // Compensation & Employement Details
        'employment_status',
        'fund_source_id',
        'salary_grade_id',

        // Position Details
        'position_id',
        'item_classification',
        'number',
        'date_of_creation',

        // Designation and assignment Details
        'designation',
        'date_of_designation',
        'special_order_number',
        // Position History and Vacancy Tracking
        'status',
        'mode_of_accession',
        'date_filled_up',
        'history_of_position',
        'former_incumbent',
        'mode_of_separation',
        'date_of_vacant',
        'remarks_of_vacancy',
        'status_of_vacant_position',
        'direct_contact_exposure_with_client',
        'remarks',
    ];

    /**
     * The attributes that should be eager-loaded
     *
     * @var array<int, string>
     */
    protected $with = [
        'division',
        'sectionOrUnit',
        'program',
        'office',
        'psipop',
        'position',
        'fundSource',
        'salaryGrade',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [

        'date_of_creation' => 'date:Y-m-d',
        'date_of_designation' => 'date:Y-m-d',
        'date_filled_up' => 'date:Y-m-d',
        'date_of_vacant' => 'date:Y-m-d',
        'status' => ItemStatus::class,
        'employment_status' => EmploymentStatus::class,
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
                StatusFilter::class,
                EmploymentStatusFilter::class,
            ])
            ->thenReturn();
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function sectionOrUnit(): BelongsTo
    {
        return $this->belongsTo(SectionOrUnit::class, 'section_or_unit_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function psipop(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'psipop_id');
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }

    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class, 'salary_grade_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}
