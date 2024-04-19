<?php

namespace App\Models;

use App\Enums\MfaAuthTypes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaKey extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'auth_type',
        'auth_key',
        'expires_at',
        'verified_at',
    ];

    /**
     * The values that should be cast
     *
     * @var string[]
     */
    protected $casts = [
        'auth_type' => MfaAuthTypes::class,
    ];

    /**
     * An MFA Key belongs to exactly one user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
