<?php

namespace App\Models\ComprehensiveRecords;

use App\Enums\ExtensionNameCategory;
use App\Enums\FamilyMemberCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualFamily extends Model
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
        'first_name',
        'last_name',
        'middle_name',
        'ext_name',
        'occupation',
        'employers_business_name',
        'business_address',
        'telephone_no',
        'date_of_birth',
        'class',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ext_name' => ExtensionNameCategory::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
        'class' => FamilyMemberCategory::class,
        'date_of_birth' => 'date:Y-m-d',
    ];

    /**
     * Dynamic computed attributes
     *
     * @var array<int, string>
     */
    protected $appends = [
        'full_name',
    ];

    /**
     * @Appended
     * Create full_name attribute
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function () {
            $firstName = $this->first_name;
            $lastName = $this->last_name;
            $middleName = $this->middle_name;
            $extName = $this->ext_name;

            $fullName = "$firstName";

            if ($middleName) {
                $fullName .= " $middleName";
            }

            $fullName .= " $lastName";

            if ($extName) {
                $fullName .= ' '.$extName->value; // Access the value of the ENUM
            }

            return trim($fullName);
        });
    }

    /**
     * A family belongs to exactly one individual
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }
}
