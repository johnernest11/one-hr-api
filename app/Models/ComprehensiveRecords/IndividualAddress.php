<?php

namespace App\Models\ComprehensiveRecords;

use App\Models\Address\Barangay;
use App\Models\Address\City;
use App\Models\Address\Province;
use App\Models\Address\Region;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualAddress extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'residential_house_block_lot_no',
        'residential_street',
        'residential_subdivision_village',
        'residential_brgy_id',
        'residential_citymun_id',
        'residential_province_id',
        'residential_region_id',
        'residential_zip_code',
        'permanent_house_block_lot_no',
        'permanent_street',
        'permanent_subdivision_village',
        'permanent_brgy_id',
        'permanent_citymun_id',
        'permanent_province_id',
        'permanent_region_id',
        'permanent_zip_code',
        'individual_basic_detail_id',
    ];

    /**
     * The relationships to eager-load
     */
    protected $with = [
        'city',
        'province',
        'region',
        'barangay',
    ];

    /**
     * An Personnel address belongs to a PDS
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }

    /**
     * An address is part of a barangay
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * An address is part of a city/municipality
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * An address is part of a province
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * An address is part of a region
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
