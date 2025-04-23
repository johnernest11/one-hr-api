<?php

namespace App\Models\Libraries;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
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
        'head_user_id',
        'added_by_user_id',
        'last_modified_by_user_id',
    ];

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }
}
