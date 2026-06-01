<?php

namespace App\Models\Libraries;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocatorActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'data',
        'selectable',
        'parent_id',
    ];

    protected $casts = [
        'selectable' => 'boolean',
    ];

    /**
     * Get the parent node.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(LocatorActivity::class, 'parent_id');
    }

    /**
     * Get the immediate children nodes, recursively loading their children.
     */
    public function children(): HasMany
    {
        return $this->hasMany(LocatorActivity::class, 'parent_id')->with('children');
    }
}
