<?php

namespace App\Models;

use App\Enums\VerificationMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerificationFactor extends Model
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
        'enrolled_at',
    ];

    /**
     * The values that should be cast
     *
     * @var string[]
     */
    protected $casts = [
        'type' => VerificationMethod::class,
        'secret' => 'encrypted',
        'enrolled_at' => 'datetime',
    ];

    /**
     * An MFA Credential belongs to exactly one user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A Verification Factor has many Backup Codes
     */
    public function backupCodes(): HasMany
    {
        return $this->hasMany(VfBackupCode::class);
    }
}
