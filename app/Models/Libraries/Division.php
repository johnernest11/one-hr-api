<?php

namespace App\Models\Libraries;

use App\Models\SectionOrUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    /**
     * The properties that are mass-assignable
     *
     * @var string[]
     */
    protected $fillable = [
        'id',
        'name',
        'office_id',
        'head_user_id',
        'added_by_user_id',
        'last_modified_by_user_id',
    ];

    public function offices(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function sectionOrUnits(): HasMany
    {
        return $this->hasMany(SectionOrUnit::class);
    }
}
