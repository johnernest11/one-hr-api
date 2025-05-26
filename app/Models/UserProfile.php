<?php

namespace App\Models;

use App\Enums\SexualCategory;
use App\Models\Address\Address;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Services\CloudStorageServices\CloudStorageManager;
use DateTimeHelper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $connection = 'mysql';

    protected $table = 'user_profiles';

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
        'mobile_number',
        'telephone_number',
        'sex',
        'birthday',
        'profile_picture_path',
    ];

    /**
     * Relationships to eager-load
     *
     * @var array<int, string>
     */
    protected $with = [
        'address',
    ];

    /**
     * Dynamic computed attributes
     *
     * @var array<int, string>
     */
    protected $appends = [
        'full_name',
        'profile_picture_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'user_id',
        'profile_picture_path',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birthday' => 'date:Y-m-d',
        'sex' => SexualCategory::class, // Laravel 9 enum casting. @see https://laravel.com/docs/9.x/releases
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (UserProfile $userProfile) {
            if ($userProfile->mobile_number) {
                $userProfile->mobile_number = DateTimeHelper::appendTimestamp(
                    $userProfile->mobile_number,
                    '::deleted_'
                );
                $userProfile->saveQuietly();
            }
        });
    }

    /**
     * A profile belongs to exactly one user
     *
     * @returns BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * A user has one address
     *
     * @returns HasOne
     */
    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    /**
     * A user belongs to one individual basic detail
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }

    public function accomplishmentReport(): HasMany
    {
        return $this->hasMany(AccomplishmentReport::class);
    }

    /**
     * @Appended
     * Create middle_initial attribute
     */
    protected function middleInitial(): Attribute
    {
        return Attribute::get(function () {
            $middleName = $this->middle_name;
            $middleInitial = ! empty($middleName) ? strtoupper(substr(trim($middleName), 0, 1)).'.' : '';

            return trim($middleInitial);
        });
    }

    /**
     * @Appended
     * Create full_name_w_middle_initial attribute
     */
    protected function fullNameWMiddleInitial(): Attribute
    {
        return Attribute::get(function () {
            $firstName = $this->first_name;
            $lastName = $this->last_name;
            $middleInitial = $this->middle_initial;
            $extName = $this->ext_name;

            if ($middleInitial) {
                $fullName = "$firstName $middleInitial $lastName $extName";

                return trim($fullName);
            }

            $fullName = "$firstName $lastName $extName";

            return trim($fullName);
        });
    }

    public function getInitials($name)
    {
        return $name ? strtoupper(substr($name, 0, 1)) : ''; // Handle nulls
    }

    /**
     * @Appended
     * Create initials attribute
     */
    protected function initials(): Attribute
    {
        return Attribute::get(function () {

            $firstNameInitial = $this->getInitials($this->first_name);
            $lastNameInitial = $this->getInitials($this->last_name);
            $middleNameInitial = $this->getInitials($this->middle_name);

            $initials = "$firstNameInitial$middleNameInitial$lastNameInitial";

            return trim($initials);
        });
    }

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

            if ($middleName) {
                $fullName = "$firstName $middleName $lastName $extName";

                return trim($fullName);
            }

            $fullName = "$firstName $lastName $extName";

            return trim($fullName);
        });
    }

    /**
     * @Appended
     * Create a profile_picture_url attribute
     */
    protected function profilePictureUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->profile_picture_path) {
                return null;
            }

            $cloudFileManager = resolve(CloudStorageManager::class);

            return $cloudFileManager->generateTmpUrl($this->profile_picture_path, 60 * 3);
        });
    }
}
