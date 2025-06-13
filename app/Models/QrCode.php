<?php

namespace App\Models;

use App\Models\ComprehensiveRecords\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QrCode extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'qr_code_value',
        'last_generated_at',
        'is_active',
    ];

    /**
     * A QR Code belongs to an employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
