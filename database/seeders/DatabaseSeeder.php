<?php

namespace Database\Seeders;

use Error;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The list of seeder classes to be executed in the defined order when running
     * Laravel database seeding commands, primarily `php artisan db:seed` and `php artisan migrate --seed`.
     *
     * This property defines the sequence in which the seeders will be run.
     * Each element should be the fully qualified class name of a seeder class
     * inheriting from `CiCdCompliantSeeder`. Developers can free add more seeders in this array.
     *
     * @var array|string[]
     */
    private array $seeders = [
        RolesAndPermissionsSeeder::class,
        RegionsSeeder::class,
        ProvincesSeeder::class,
        CitiesSeeder::class,
        BarangaysSeeder::class,
        AppSettingsSeeder::class,
    ];

    /**
     * Seed the application's database.
     *
     * Iterates through the defined `$seeders` array and executes each seeder class.
     * Skips seeders that don't ensure data **idempotence** during seeding
     * (as determined by the `shouldRun` method in the inheriting seeder class).
     * Idempotence guarantees that running the seeder multiple times won't introduce
     * duplicate data or unintended side effects.
     */
    public function run(): void
    {
        foreach ($this->seeders as $seeder) {
            if (! is_subclass_of($seeder, CiCdCompliantSeeder::class)) {
                throw new Error("$seeder is not a subclass of ".CiCdCompliantSeeder::class);
            }

            /** @var CiCdCompliantSeeder $seederInstance * */
            $seederInstance = resolve($seeder);
            if ($seederInstance->shouldRun()) {
                $this->call($seeder);
            }
        }
    }
}
