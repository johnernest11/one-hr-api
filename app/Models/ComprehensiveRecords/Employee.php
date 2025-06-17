<?php

namespace App\Models\ComprehensiveRecords;

use App\Models\Item;
use App\Models\Libraries\Division;
use App\Models\Libraries\Office;
use App\Models\Libraries\Program;
use App\Models\Libraries\SalaryGrade;
use App\Models\Libraries\SectionOrUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

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
    public function salaryGrade(): HasOne
    {
        return $this->hasOne(SalaryGrade::class);
    }

    /**
     * An employee has one section or unit
     */
    public function sectionOrUnit(): HasOne
    {
        return $this->hasOne(SectionOrUnit::class);
    }

    /**
     * An employee has one division
     */
    public function division(): HasOne
    {
        return $this->hasOne(Division::class);
    }

    /**
     * An employee has one office
     */
    public function office(): HasOne
    {
        return $this->hasOne(Office::class);
    }

    /**
     * An employee has one program
     */
    public function program(): HasOne
    {
        return $this->hasOne(Program::class);
    }
}
