<?php

namespace App\Models;

use App\QueryFilters\AccomplishmentReport\StatusFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pipeline\Pipeline;

class CompensatoryReport extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'compensatory_reports';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ctdo_period',
        'ctdo_supervisor_notes',
        'ctdo_status',
        'user_profile_id',
    ];

    /**
     * @Scope
     * Pipeline for HTTP query filters
     */
    public function scopeFiltered(Builder $builder): Builder
    {
        return app(Pipeline::class)
            ->send($builder->where('user_profile_id', '=', auth()->user()->userProfile->id)->with('rows'))
            ->through([
                StatusFilter::class,
            ])
            ->thenReturn();
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CTDORows::class);
    }

    public function userProfile(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class);
    }
}
