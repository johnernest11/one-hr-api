<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VfBackupCode extends Model
{
    use HasFactory;

    protected $table = 'vf_backup_codes';

    protected $fillable = [
        'verification_factor_id',
        'code',
        'used_at',
    ];

    protected $casts = [
        'code' => 'encrypted',
    ];

    /**
     * A Backup Code belongs to a Verification Factor
     */
    public function verificationFactor(): BelongsTo
    {
        return $this->belongsTo(VerificationFactor::class);
    }
}
