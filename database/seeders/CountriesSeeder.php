<?php

namespace Database\Seeders;

use App\Models\Libraries\Country;
use Carbon\Carbon;

/**
 * Source for JSON dump: https://github.com/mledoze/countries
 *
 * The following fields are taken from the source:
 *
 * - name
 *   - common - common name in english
 *   - official - official name in english
 *   - native - list of all native names [This will be excluded when seeding.]
 *     - key: three-letter ISO 639-3 language code
 *     - value: name object
 *       - key: official - official name translation
 *       - key: common - common name translation
 * - code ISO 3166-1 alpha-2 (cca2)
 * - code ISO 3166-1 numeric (ccn3)
 * - code ISO 3166-1 alpha-3 (cca3)
 * - code International Olympic Committee (cioc)
 */
class CountriesSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/countries.json'));
        $countriesJson = json_decode($rawData, true);

        $countries = [];
        foreach ($countriesJson as $country) {
            $countries[] = [
                'common_name' => $country['name']['common'],
                'official_name' => $country['name']['official'],
                'cca2' => $country['cca2'],
                'ccn3' => $country['ccn3'],
                'cca3' => $country['cca3'],
                'cioc' => $country['cioc'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        Country::insert($countries);
    }

    /**
     * {@inheritDoc}
     */
    protected function tableName(): string
    {
        return app(Country::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
