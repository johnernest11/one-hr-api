<?php

namespace App\Models\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualVoluntaryWork extends Model
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
        'is_current_org',
        'org_name',
        'org_address',
        'from',
        'to',
        'number_of_hours',
        'position_nature_of_work',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_current_org' => 'boolean',
        'from' => 'date:Y-m-d',
        'to' => 'date:Y-m-d',
    ];

    /**
     * A voluntary work belongs to an individual
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }
}
