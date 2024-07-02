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
    /**
     * This method must be implemented by the inheriting seeder class to indicate
     * whether its data seeding logic guarantees **idempotence**. Idempotence ensures
     * that running the seeder multiple times won't introduce duplicate data or
     * unintended side effects. This is crucial for maintaining data integrity
     * in CI/CD pipelines where seeders might be executed repeatedly during deployments
     * or tests.
     *
     * The seeder can utilize the helper methods provided in this class
     * (and Developers, feel free to add more methods) to achieve idempotence.
     *
     * This method **does not** control whether the seeder is actually executed.
     * The DatabaseSeeder class handles that logic based on the return value.
     *
     * @return bool True if the seeder's logic guarantees idempotence and can be safely run
     *              in CI/CD pipelines, false otherwise.
     */
    abstract public function shouldRun(): bool;

    /**
     * Returns the name of the database table that this seeder is intended for.
     *
     * @return string The name of the database table.
     */
    abstract protected function tableName(): string;

    /**
     * Helper method to check if the specified table is empty.
     *
     * @param  string  $pkName  (Optional) The primary key name of the table. Defaults to 'id'.
     * @return bool True if the table is empty, false otherwise.
     */
    protected function tableIsEmpty(string $pkName = 'id'): bool
    {
        $tableName = $this->tableName();
        $tableHasRecords = DB::table($tableName)->count($pkName) > 0;

        if ($tableHasRecords) {
            Log::info("$$tableName table already seeded");
        }

        return ! $tableHasRecords;
    }

    /**
     * A helper method to check if specific records already exist in a database table.
     * This method is currently not implemented but can be added if needed in future seeders.
     *
     * @param  string  $column  The column name to check for matching records.
     * @param  array  $records  An array of values to check for in the specified column.
     * @return bool True if none of the records exist in the table, false otherwise. (Currently always true)
     */
    protected function recordsNotInTable(string $column, array $records): bool
    {
        /** TODO: Implement if needed */
        return true;
    }
}
