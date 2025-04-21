<?php

namespace App\Models\Libraries;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Source for JSON dump: https://github.com/mledoze/countries
 *
 * The following fields are taken from the source:
 *
 * - name
 *   - common - common name in english
 *   - official - official name in english
 * - code ISO 3166-1 alpha-2 (cca2)
 * - code ISO 3166-1 numeric (ccn3)
 * - code ISO 3166-1 alpha-3 (cca3)
 * - code International Olympic Committee (cioc)
 */
class Country extends Model
{
    use HasFactory;

    /**
     * The properties that are mass-assignable
     *
     * @var string[]
     */
    protected $fillable = [
        'id',
        'common_name',
        'official_name',
        'cca2',
        'ccn3',
        'cca3',
        'cioc',
    ];
}
