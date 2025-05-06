<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\ItemStatus;
use App\Models\Libraries\FundSource;
use App\Models\Libraries\Position;
use App\QueryFilters\Item\EmploymentStatusFilter;
use App\QueryFilters\Item\StatusFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pipeline\Pipeline;

class Item extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'number',
        'date_of_creation',
        'status',
        'date_filled_up',
        'employment_status',
        'position_id',
        'fund_source_id',
    ];

    /**
     * The attributes that should be eager-loaded
     *
     * @var array<int, string>
     */
    protected $with = [
        'position',
        'fundSource',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_creation' => 'date:Y-m-d',
        'date_filled_up' => 'date:Y-m-d',
        'status' => ItemStatus::class,
        'employment_status' => EmploymentStatus::class,
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
                StatusFilter::class,
                EmploymentStatusFilter::class,
            ])
            ->thenReturn();
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }
}
