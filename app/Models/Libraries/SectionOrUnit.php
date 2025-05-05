<?php

namespace App\Models\Libraries;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionOrUnit extends Model
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
        'division_id',
        'head_user_id',
        'added_by_user_id',
        'last_modified_by_user_id',
    ];

    public function divisions(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
}
