<?php

namespace App\Models\ComprehensiveRecords;

use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualWorkExperience extends Model
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
        'is_current_work',
        'inclusive_date_from',
        'inclusive_date_to',
        'position_title',
        'department_agency_office_company',
        'monthly_salary',
        'salary_grade_id',
        'custom_salary_grade',
        'status_of_appointment',
        'is_gov_service',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_current_work' => 'boolean', // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'inclusive_date_from' => 'date:Y-m-d', // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'inclusive_date_to' => 'date:Y-m-d', // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'status_of_appointment' => EmploymentStatus::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'is_gov_service' => 'boolean', // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
    ];

    /**
     * Ensure that everytime a record is saved, only one is_current_work is set to true for every individual.
     *
     * @return void
     */
    protected static function booted()
    {
        static::saving(function ($individualWorkExperience) {
            if ($individualWorkExperience->is_current_work) {
                static::where('individual_basic_detail_id', $individualWorkExperience->individual_basic_detail_id)
                    ->where('is_current_work', true)
                    ->where('id', '!=', $individualWorkExperience->id) // Avoid deactivating the current model if it's being updated
                    ->update(['is_current_work' => false]);
            }
        });
    }

    /**
     * A work experience belongs to an individual
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }
}
