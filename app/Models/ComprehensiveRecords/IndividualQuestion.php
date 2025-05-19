<?php

namespace App\Models\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualQuestion extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'individual_basic_detail_id',
        /* ------------------------------- Question 34 ------------------------------ */
        'q34_a',
        'q34_b',
        'q34_details',
        /* ------------------------------- Question 35 ------------------------------ */
        'q35_a',
        'q35_a_details',
        'q35_b',
        'q35_b_date_filed',
        'q35_b_status',
        /* ------------------------------- Question 36 ------------------------------ */
        'q36',
        'q36_details',
        /* ------------------------------- Question 37 ------------------------------ */
        'q37',
        'q37_details',
        /* ------------------------------- Question 38 ------------------------------ */
        'q38_a',
        'q38_a_details',
        'q38_b',
        'q38_b_details',
        /* ------------------------------- Question 39 ------------------------------ */
        'q39',
        'q39_details', //@todo change to country_id
        /* ------------------------------- Question 40 ------------------------------ */
        'q40_a_indigenous_group',
        'q40_a_details',
        'q40_b_pwd',
        'q40_b_details',
        'q40_c_solo_parent',
        'q40_c_details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'q35_b_date_filed' => 'date:Y-m-d',
        'q34_a' => 'boolean',
        'q34_b' => 'boolean',
        'q35_a' => 'boolean',
        'q35_b' => 'boolean',
        'q36' => 'boolean',
        'q37' => 'boolean',
        'q38_a' => 'boolean',
        'q38_b' => 'boolean',
        'q39' => 'boolean',
        'q40_a_indigenous_group' => 'boolean',
        'q40_b_pwd' => 'boolean',
        'q40_c_solo_parent' => 'boolean',
    ];

    /**
     * An question belongs to an individual
     */
    public function individualBasicDetail(): BelongsTo
    {
        return $this->belongsTo(IndividualBasicDetail::class);
    }
}
