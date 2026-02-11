<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CTDORows extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'days_of_the_week',
        'work_date',
        'time_start',
        'time_end',
        'accomplishment',
        'authorized_claim',
        'compensatory_reports_id',
    ];

    public function compensatoryReport(): BelongsTo
    {
        return $this->belongsTo(CompensatoryReport::class);
    }
}
