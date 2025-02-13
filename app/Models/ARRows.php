<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ARRows extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'week_num',
        'dates_in_week',
        'specific_activity',
        'highlights',
        'accomplishment_reports_id',
    ];

    public function accomplishmentReport(): BelongsTo
    {
        return $this->belongsTo(AccomplishmentReport::class);
    }
}
