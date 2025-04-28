<?php

namespace App\Models\ComprehensiveRecords;

use App\Enums\AcademicLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualEducationalBackground extends Model
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
        'schools_name',
        'education_description',
        'level',
        'period_of_attendance_from',
        'period_of_attendance_to',
        'highest_level_units_earned',
        'year_graduated',
        'scholarship_academic_honors_received',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => AcademicLevel::class,
    ];

    /**
     * An educational background belongs to an individual
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }
}
