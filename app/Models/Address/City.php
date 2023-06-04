<?php

namespace App\Models\Address;

use App\Enums\MunicipalClassification;
use App\QueryFilters\Address\Classification;
use App\QueryFilters\Address\Code;
use App\QueryFilters\Address\IsCapital;
use App\QueryFilters\Address\Province as ProvinceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pipeline\Pipeline;

class City extends Model
{
    use HasFactory;

    /**
     * The properties that are mass-assignable
     *
     * @var string[]
     */
    protected $fillable = [
        'code',
        'province_id',
        'name',
        'full_name',
        'alt_name',
        'classification',
        'is_capital',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_capital' => 'boolean',
        'classification' => MunicipalClassification::class,
    ];

    /**
     * @Scope
     * Pipeline for HTTP query filters
     */
    public function scopeFiltered(Builder $builder): Builder
    {
        return app(Pipeline::class)
            ->send($builder)
            ->through([
                Code::class,
                ProvinceFilter::class,
                IsCapital::class,
                Classification::class,
            ])
            ->thenReturn();
    }

    /**
     * A City comprises an address
     *
     * @returns HasMany
     */
    protected function address(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * A City belongs to province
     *
     * @returns BelongsTo
     */
    protected function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
