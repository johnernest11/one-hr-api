<?php

namespace App\Models\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualReference extends Model
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
        'name',
        'address',
        'tel_no',
    ];

    /**
     * A reference belongs to an individual
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }
}
