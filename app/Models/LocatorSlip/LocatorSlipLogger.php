<?php

namespace App\Models\LocatorSlip;

use App\Enums\ApprovalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocatorSlipLogger extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'locator_slip_id',
        'date',
        'time_in',
        'time_out',
        'destination',
        'purpose',
        'approved_for',
        'duration',
        'remarks',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'approved_for' => ApprovalType::class,
    ];

    public function locatorSlip(): BelongsTo
    {
        return $this->belongsTo(LocatorSlip::class);
    }
}
