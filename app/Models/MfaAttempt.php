<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class MfaAttempt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'steps',
        'auth_metadata',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'steps' => 'array',
        'auth_metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * @Attribute
     * Hash the token whenever it is set
     */
    protected function token(): Attribute
    {
        return Attribute::set(
            fn ($value) => Hash::make($value)
        );
    }

    /**
     * An MFA Attempt belongs to exactly one user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
