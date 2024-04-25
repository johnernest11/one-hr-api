<?php

namespace App\Models;

use App\Enums\MfaAuthTypes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaCredential extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'secret',
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
     * An MFA Credential belongs to exactly one user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
