<?php

namespace App\Services;

use Cache;
use Illuminate\Support\Facades\DB;
use Schema;

class DbSchemaInspector
{
    /**
     * Get all the columns in a database table
     */
    public function getAllColumns(string $tableName): array
    {
        return Cache::rememberForever($this->getAllColumnsCacheKey($tableName), function () use ($tableName) {
            return Schema::getColumnListing($tableName);
        });
    }

    /**
     * Get all the tables from the database
     */
    public function getAllTables(): array
    {
        return Cache::rememberForever($this->getAllTablesCacheKey(), function () {
            $tables = DB::select('SHOW TABLES');

            // strip off all the objects and keys.
            return array_map(fn ($table) => array_values(get_mangled_object_vars($table))[0], $tables);
        });
    }

    /**
     * Check if a column exists in a database table
     */
    public function checkIfColumnExists(string $tableName, string $columnName): bool
    {
        $allColumns = $this->getAllColumns($tableName);

        return in_array($columnName, $allColumns);
    }

    /**
     * Check if a given table exists
     */
    public function checkIfTableExists(string $tableName): bool
    {
        $allTables = $this->getAllTables();

        return in_array($tableName, $allTables);
    }

    /**
     * Get all column names of a table except
     */
    public function getAllColumnNamesExcept(string $tableName, array $excludedColumns): array
    {
        $allColumns = $this->getAllColumns($tableName);

        return array_diff($allColumns, $excludedColumns);
    }

    /**
     * Returns the cache key for fetching all the column names of database table
     */
    private function getAllColumnsCacheKey($tableName): string
    {
        // Returns the project's absolute path for the migration folder
        // ex. '/Users/jegramos/projects/sunrise-project/database/migrations'
        $migrationPath = database_path('migrations');

        // Get the Unix timestamp of when this file type was last modified
        $lastModified = filemtime($migrationPath);

        // This key will change when the contents of the migration folder is modified
        // e.x. When a migration file (or any file) is added, deleted, or renamed.
        // This will not change if the CONTENTS of the file is edited
        return "schema:$lastModified:$tableName:columns";
    }

    /**
     * Returns the cache key for fetching all the column names of database table
     */
    private function getAllTablesCacheKey(): string
    {
        // Returns the project's absolute path for the migration folder
        // ex. '/Users/jegramos/projects/sunrise-project/database/migrations'
        $migrationPath = database_path('migrations');

        // Get the Unix timestamp of when this file type was last modified
        $lastModified = filemtime($migrationPath);

        // This key will change when the contents of the migration folder is modified
        // e.x. When a migration file (or any file) is added, deleted, or renamed.
        // This will not change if the CONTENTS of the file is edited
        return "schema:$lastModified:all-tables";
    }
}
