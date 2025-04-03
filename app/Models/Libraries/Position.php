<?php

namespace App\Models\Libraries;

use App\Models\Item;
use App\QueryFilters\Libraries\PositionLevelFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pipeline\Pipeline;

class Position extends Model
{
    use HasFactory;

    /**
     * The properties that are mass-assignable
     *
     * @var string[]
     */
    protected $fillable = [
        'id',
        'title',
        'parenthetical_title',
        'level',
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
                PositionLevelFilter::class,
            ])
            ->thenReturn();
    }

    // @todo ADD RELATIONSHIP TO ITEMS ONCE IT IS CREATED

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
