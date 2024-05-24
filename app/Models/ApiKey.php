<?php

namespace App\Models;

use App\QueryFilters\Generic\ActiveFilter;
use App\Services\ApiKeyManager;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class ApiKey extends Model implements Authenticatable
{
    use HasFactory;
    use HasRoles;
    use SoftDeletes;

    /**
     * Spatie needs this if multiple auth guards are used
     *
     * @see https://spatie.be/docs/laravel-permission/v6/basic-usage/multiple-guards
     */
    protected string $guard_name = 'api_key';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'user_id',
        'expires_at',
        'active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'key',
    ];

    /**
     * The attributes that should be eager-loaded
     *
     * @var array<int, string>
     */
    protected $with = [
        'user',
    ];

    /**
     * This temporary property populated when the key is just created.
     *
     * @see ApiKeyManager::create()
     */
    public ?string $rawKeyValue = null;

    /**
     * @Scope
     * Pipeline for HTTP query filters
     */
    public function scopeFiltered(Builder $builder): Builder
    {
        return app(Pipeline::class)
            ->send($builder)
            ->through([
                ActiveFilter::class,
            ])
            ->thenReturn();
    }

    /**
     * Every API Key belongs to a user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @Scope
     *
     * Scope a query to only include active api keys.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Check if the key has not expired yet
     */
    public function isExpired(): bool
    {
        // We consider it active if there is no expires_at set
        if (is_null($this->expires_at)) {
            return true;
        }

        return Carbon::now()->greaterThanOrEqualTo($this->expires_at);
    }

    /**
     * @Attribute
     * Hash the key whenever it is set
     */
    protected function key(): Attribute
    {
        return Attribute::set(
            fn ($value) => Hash::make($value)
        );
    }

    /**
     * We've implemented this method, so we can use the ApiKey model for authentication
     *
     * @see Authenticatable
     */
    public function getAuthIdentifierName(): string|int
    {
        return 'id';
    }

    /**
     * We've implemented this method, so we can use the ApiKey model for authentication
     *
     * @see Authenticatable
     */
    public function getAuthIdentifier(): string|int
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * API keys don't have passwords
     *
     * @see Authenticatable
     */
    public function getAuthPassword(): null
    {
        return null;
    }

    /**
     * API keys don't have passwords
     *
     * @see Authenticatable
     */
    public function getRememberToken(): null
    {
        return null;
    }

    /**
     * We do nothing. API Keys don't need a remember token
     *
     * @see Authenticatable
     */
    public function setRememberToken($value)
    {
        // Nothing
    }

    /**
     * API keys don't have a remember token
     *
     * @see Authenticatable
     */
    public function getRememberTokenName(): null
    {
        return null;
    }
}
