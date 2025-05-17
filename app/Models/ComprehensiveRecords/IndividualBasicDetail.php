<?php

namespace App\Models\ComprehensiveRecords;

use App\Enums\BloodType;
use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\ExtensionNameCategory;
use App\Enums\SexualCategory;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualBasicDetail extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'ext_name',
        'birthday',
        'mobile_number',
        'telephone_number',
        'sex',
        'email',
        'place_of_birth',
        'civil_status',
        'height',
        'weight',
        'blood_type',
        'gsis_no',
        'pag_ibig_no',
        'philhealth_no',
        'sss_no',
        'tin',
        'citizenship',
        'citizenship_acquisition',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ext_name' => ExtensionNameCategory::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'civil_status' => CivilStatus::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'blood_type' => BloodType::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'birthday' => 'date:Y-m-d',
        'sex' => SexualCategory::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'citizenship' => Citizenship::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'citizenship_acquisition' => CitizenshipAcquisition::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
    ];

    /**
     * An individual has one user profile
     */
    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    // @todo Update relations until completed
    /* -------------------------------------------------------------------------- */
    /*                               C1 Starts Here                               */
    /* -------------------------------------------------------------------------- */

    /**
     * An individual has one employee record
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * An individual has one Address
     */
    public function individualAddress(): HasOne
    {
        return $this->hasOne(IndividualAddress::class);
    }

    /**
     * A individual has one Contact Information
     */
    public function individualContactInfo(): HasOne
    {
        return $this->hasOne(IndividualContactInfo::class);
    }

    /**
     * An individual has many family members
     */
    public function individualFamily(): HasMany
    {
        return $this->hasMany(IndividualFamily::class);
    }

    /**
     * An individual has many educational background
     */
    public function individualEducationalBackground(): HasMany
    {
        return $this->hasMany(IndividualEducationalBackground::class);
    }

    /* -------------------------------------------------------------------------- */

    /* -------------------------------------------------------------------------- */
    /*                               C2 starts here                               */
    /* -------------------------------------------------------------------------- */

    /**
     * An individual has many eligibilities
     */
    public function individualEligibility(): HasMany
    {
        return $this->hasMany(IndividualEligibility::class);
    }

    /**
     * An individual has many work experiences
     */
    public function individualWorkExperience(): HasMany
    {
        return $this->hasMany(IndividualWorkExperience::class);
    }

    /* -------------------------------------------------------------------------- */

    /* -------------------------------------------------------------------------- */
    /*                               C3 starts here                               */
    /* -------------------------------------------------------------------------- */

    /**
     * An individual has many work voluntary work
     */
    public function individualVoluntaryWork(): HasMany
    {
        return $this->hasMany(IndividualVoluntaryWork::class);
    }

    /**
     * An individual has many learning and development programs attended
     */
    public function individualLnd(): HasMany
    {
        return $this->hasMany(IndividualLnd::class);
    }

    /**
     * An individual has many skills and hobbies
     */
    public function individualSkillsHobby(): HasMany
    {
        return $this->hasMany(IndividualSkillsHobby::class);
    }

    /**
     * An individual has many recognitions
     */
    public function individualRecognition(): HasMany
    {
        return $this->hasMany(IndividualRecognition::class);
    }

    /**
     * An individual has many memberships
     */
    public function individualMembership(): HasMany
    {
        return $this->hasMany(IndividualMembership::class);
    }

    /* -------------------------------------------------------------------------- */

    /* -------------------------------------------------------------------------- */
    /*                               C4 starts here                               */
    /* -------------------------------------------------------------------------- */

    /**
     * A individual has one question
     */
    public function individualQuestion(): HasOne
    {
        return $this->hasOne(IndividualQuestion::class);
    }
}
