<?php

namespace App\Models\DailyTimeRecords;

use App\Enums\DocumentStatus;
use App\Models\ComprehensiveRecords\Employee;
use App\QueryFilters\DailyTimeRecords\DateFilter;
use App\QueryFilters\DailyTimeRecords\DivisionFilter;
use App\QueryFilters\DailyTimeRecords\OfficeFilter;
use App\QueryFilters\DailyTimeRecords\SectionFilter;
use App\QueryFilters\DailyTimeRecords\TimeInOrOutFilter;
use Carbon\Carbon;
use DB;
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
                ->whereNull('employees.deleted_at')
                ->join('offices', 'employees.office_id', '=', 'offices.id')
                ->join('divisions', 'employees.division_id', '=', 'divisions.id')
                ->join('section_or_units', 'employees.section_or_unit_id', '=', 'section_or_units.id')
                ->join('individual_basic_details', 'employees.individual_basic_detail_id', '=', 'individual_basic_details.id')
                ->join('time_logs', 'daily_time_records.id', '=', 'time_logs.daily_time_record_id')
                ->select(
                    'daily_time_records.date AS dtr_date',
                    'time_logs.id AS time_log_id',
                    'time_logs.is_in',
                    'time_logs.scanned_time',
                    'time_logs.date AS time_log_date',
                    'time_logs.captured_image_path',
                    'employees.id_number',
                    'individual_basic_details.first_name',
                    'individual_basic_details.middle_name',
                    'individual_basic_details.last_name',
                    'individual_basic_details.ext_name',
                    'employees.office_id',
                    'offices.name AS office_name',
                    'divisions.name AS division_name',
                    'section_or_units.name AS section_name',
                )
                ->orderBy('dtr_date', 'desc')
                ->orderBy('time_logs.scanned_time', 'desc')
            )
            ->through([
                OfficeFilter::class,
                DivisionFilter::class,
                SectionFilter::class,
                DateFilter::class,
                TimeInOrOutFilter::class,
            ])
            ->thenReturn();
    }

    public function scopeCurrentlyInsideOnly(Builder $query): Builder
    {
        $today = Carbon::now()->toDateString();

        $latestScanPerEmployee = DB::table('time_logs as tl')
            ->join('daily_time_records as dtr', 'tl.daily_time_record_id', '=', 'dtr.id')
            ->where('tl.date', $today)
            ->groupBy('dtr.employee_id')
            ->select([
                'dtr.employee_id',
                DB::raw('MAX(tl.scanned_time) as latest_time'),
            ]);

        return $query
            ->whereNull('employees.deleted_at')
            ->joinSub($latestScanPerEmployee, 'latest_logs', function ($join) {
                $join->on('employees.id', '=', 'latest_logs.employee_id');
                $join->on('time_logs.scanned_time', '=', 'latest_logs.latest_time');
            })
            ->where('time_logs.is_in', 1);
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
