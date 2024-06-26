<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use Log;

/**
 * This abstract class provides methods for database seeders to ensure idempotence
 * and compliance with CI/CD pipelines. Idempotence guarantees that seeding can be run
 * multiple times without introducing duplicate data or causing unintended side effects.
 * This is crucial for maintaining data integrity in CI/CD environments where seeders
 * might be executed repeatedly during deployments or tests.
 */
abstract class CiCdCompliantSeeder extends Seeder
{
    protected function tableNotEmpty(): bool
    {
        $tableName = $this->tableName();
        $tableHasRecords = DB::table($tableName)->count() > 0;

        if ($tableHasRecords) {
            Log::info("$$tableName table already seeded");
        }

        return $tableHasRecords;
    }

    abstract protected function tableName(): string;
}
