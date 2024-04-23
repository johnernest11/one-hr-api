<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class MfaVerification extends Model
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
        'mfa_steps',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'mfa_steps' => 'array',
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
     * This temporary property populated when the token is just created.
     *
     * @see TODO: Add there to find this
     */
    public ?string $rawTokenValue = null;

    /**
     * @Attribute
     * Hash the token whenever it is set
     */
    public function token(): Attribute
    {
        return Attribute::set(fn ($value) => Hash::make($value));
    }
}
