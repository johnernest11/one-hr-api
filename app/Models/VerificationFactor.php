<?php

namespace App\Models;

use App\Enums\VerificationMethod;
use Crypt;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    /**
     * The values that should be cast
     *
     * @var string[]
     */
    protected $casts = [
        'type' => VerificationMethod::class,
    ];

    /**
     * @Attribute
     * Secrets are encrypted when stored, and decrypted when retrieved
     */
    protected function secret(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => Crypt::decryptString($value),
            set: fn (?string $value) => Crypt::encryptString($value),
        );
    }

    /**
     * An MFA Credential belongs to exactly one user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
