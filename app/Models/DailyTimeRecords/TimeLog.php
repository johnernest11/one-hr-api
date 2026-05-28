<?php

namespace App\Models\DailyTimeRecords;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_time_record_id',
        'date',
        'scanned_time',
        'is_in',
        'is_selected',
        'captured_image_path',
        'office_id',
        'browser_uid',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_in' => 'boolean',
        'is_selected' => 'boolean',
    ];

    protected $appends = ['captured_image_url'];

    public function dailyTimeRecord(): BelongsTo
    {
        return $this->belongsTo(DailyTimeRecord::class);
    }

    public function getCapturedImageUrlAttribute(): ?string
    {
        if (! $this->captured_image_path) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl(
            $this->captured_image_path,
            now()->addHours(8)
        );
    }
}
