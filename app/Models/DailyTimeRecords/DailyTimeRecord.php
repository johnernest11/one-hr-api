<?php

namespace App\Models\DailyTimeRecords;

use App\Enums\DocumentStatus;
use App\Models\ComprehensiveRecords\Employee;
use App\QueryFilters\DailyTimeRecords\DateFilter;
use App\QueryFilters\DailyTimeRecords\DivisionFilter;
use App\QueryFilters\DailyTimeRecords\SectionFilter;
use App\QueryFilters\DailyTimeRecords\TimeInOrOutFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pipeline\Pipeline;

class DailyTimeRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'date',
        'ut',
        'is_edit_ut',
        'ot',
        'is_missing',
        'employee_remarks',
        'hr_remarks',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_edit_ut' => 'boolean',
        'is_missing' => 'boolean',
        'status' => DocumentStatus::class,
    ];

    /**
     * @Scope
     * Pipeline for HTTP query filters
     */
    public function scopeFiltered(Builder $builder): Builder
    {
        return app(Pipeline::class)
            ->send($builder
                ->join('employees', 'daily_time_records.employee_id', '=', 'employees.id')
                ->join('divisions', 'employees.division_id', '=', 'divisions.id')
                ->join('section_or_units', 'employees.section_or_unit_id', '=', 'section_or_units.id')
                ->join('individual_basic_details', 'employees.individual_basic_detail_id', '=', 'individual_basic_details.id')
                ->join('time_logs', 'daily_time_records.id', '=', 'time_logs.daily_time_record_id')
                ->select(
                    // daily_time_records
                    'daily_time_records.date AS dtr_date',
                    // time_logs
                    'time_logs.is_in',
                    'time_logs.scanned_time',
                    'time_logs.date AS time_log_date',
                    // employees
                    'employees.id_number',
                    // individual_basic_details
                    'individual_basic_details.first_name',
                    'individual_basic_details.middle_name',
                    'individual_basic_details.last_name',
                    'individual_basic_details.ext_name',
                    // divisions
                    'divisions.name AS division_name',
                    // section_or_units
                    'section_or_units.name AS section_name',
                )
                ->orderBy('dtr_date', 'desc')
                ->orderBy('time_logs.scanned_time', 'desc')
            )
            ->through([
                DivisionFilter::class,
                SectionFilter::class,
                DateFilter::class,
                TimeInOrOutFilter::class,
            ])
            ->thenReturn();
    }

    /**
     * A QR Code belongs to an employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function timeLog(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }
}
