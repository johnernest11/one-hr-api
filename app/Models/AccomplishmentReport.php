<?php

namespace App\Models;

use App\QueryFilters\AccomplishmentReport\StatusFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pipeline\Pipeline;

class AccomplishmentReport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'period',
        'supervisor_notes',
        'status',
        'user_profile_id',
        //'position_id', hidden for now.
        //'odsu_id', hidden for now.
    ];

    /**
     * @Scope
     * Pipeline for HTTP query filters
     */
    public function scopeFiltered(Builder $builder): Builder
    {
        return app(Pipeline::class)
            ->send($builder->with('rows'))
            ->through([
                StatusFilter::class,
            ])
            ->thenReturn();
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ARRows::class);
    }

    public function userProfile(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class);
    }
}
