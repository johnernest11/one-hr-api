<?php

namespace App\Models\Address;

use App\QueryFilters\Address\Code;
use App\QueryFilters\Address\Region as RegionFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pipeline\Pipeline;

class Province extends Model
{
    use HasFactory;

    /**
     * The properties that are mass-assignable
     *
     * @var string[]
     */
    protected $fillable = [
        'code',
        'name',
        'alt_name',
        'region_id',
    ];

    /**
     * @Scope
     * Pipeline for HTTP query filter
     */
    public function scopeFiltered(Builder $builder): Builder
    {
        return app(Pipeline::class)
            ->send($builder)
            ->through([
                Code::class,
                RegionFilter::class,
            ])
            ->thenReturn();
    }

    /**
     * A province comprises an address
     *
     * @returns HasMany
     */
    protected function address(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * A province belongs to region
     *
     * @returns BelongsTo
     */
    protected function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
