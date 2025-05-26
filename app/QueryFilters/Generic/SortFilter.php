<?php

namespace App\QueryFilters\Generic;

use App\QueryFilters\Filter;
use App\Services\DbSchemaInspector;
use Illuminate\Database\Eloquent\Builder;
use Log;
use Str;

class SortFilter extends Filter
{
    protected function applyFilter(Builder $builder): Builder
    {
        $filterName = $this->getFilterName();

        // if `?sort=` is neither `asc` nor `desc`, we'll ignore the query param
        // and send the builder to the next pipe
        if (! in_array(request($filterName), ['asc', 'desc'])) {
            return $builder;
        }

        // we'll default to ID if the `sort_by` query param is not provided
        $sortBy = request('sort_by');
        if (! $sortBy) {
            return $builder->orderBy('id', request($filterName));
        }

        $tableName = (clone $builder)->getModel()->getTable();

        // we do a regular orderBy with a column on the primary table
        // Ex. ?sort_by=username (username is found directly on the users table)
        if (! $this->checkIfSortByFilterIsNested($sortBy)) {
            $schemaService = resolve(DbSchemaInspector::class);
            $columnExists = $schemaService->checkIfColumnExists($tableName, $sortBy);
            $sortBy = $columnExists ? $sortBy : 'id';

            return $builder->orderBy($sortBy, request($filterName));
        }

        // handle nested sortBy. Ex. ?sort_by=user_profile.email
        // `email` is found in a related table, <parent>[user].<related>[user_profile].email
        $builderWithJoin = $this->joinRelatedTable($sortBy, $builder);

        // if we can't do an inner join for the relationship, we'll just send a default ID sorting
        if (is_null($builderWithJoin)) {
            return $builder->orderBy('id', request($filterName));
        }

        // we do an orderBy with a column from a related table
        return $this->buildNestedOrderByQuery($sortBy, $builderWithJoin, $tableName, $filterName);
    }

    protected function getFilterName(): string
    {
        return 'sort';
    }

    /**
     * Clients may opt to search via a nested relationship
     * such as: user_profile.last_name
     */
    private function joinRelatedTable(string $sortBy, Builder $builder): ?Builder
    {
        // Extract related table from sortBy param (e.g. 'user_profile' -> 'user_profiles')
        $tableName = $this->getNestedSortByFilterRelatedTable($sortBy);

        $schemaService = resolve(DbSchemaInspector::class);
        $tableExists = $schemaService->checkIfTableExists($tableName);

        if (! $tableExists) {
            Log::debug('Database table not found', [
                'class' => self::class,
                'method' => __FUNCTION__,
                'table_name' => $tableName,
            ]);

            return null;
        }

        $parentModel = (clone $builder)->getModel();
        $parentTableName = $parentModel->getTable();
        $parentConnection = $parentModel->getConnectionName();

        // Determine the connection for the related table
        $relatedConnection = $this->getConnectionForTable($tableName);

        // If connections differ, we cannot join across databases — return null
        if ($parentConnection !== $relatedConnection) {
            Log::warning("Skipping join: parent table '{$parentTableName}' is on connection '{$parentConnection}', but related table '{$tableName}' is on connection '{$relatedConnection}'");

            return null;
        }

        $foreignKey = $this->constructForeignKey($parentTableName);

        if (! $schemaService->checkIfColumnExists($tableName, $foreignKey)) {
            Log::error('Unable to construct the foreign key', [
                'class' => self::class,
                'method' => __FUNCTION__,
                'constructed_foreign_key' => $foreignKey,
                'parent_table_name' => $parentTableName,
                'related_table_name' => $tableName,
            ]);

            return null;
        }

        return $builder->join($tableName, "$tableName.$foreignKey", '=', "$parentTableName.id");
    }

    /**
     * Return the DB connection name for a given table
     * You can customize this mapping based on your config/models.
     */
    private function getConnectionForTable(string $tableName): ?string
    {
        // Hardcoded example: adjust for your app
        $tableToConnection = [
            'users' => 'one_account',        // User model DB connection
            'user_profiles' => 'mysql',      // UserProfile model DB connection
        ];

        return $tableToConnection[$tableName] ?? null;
    }

    /**
     * Get the related table from the query filter
     * user_profile.username => user_profiles
     */
    private function getNestedSortByFilterRelatedTable($sortBy): string
    {
        return Str::plural(explode('.', $sortBy)[0]);
    }

    /**
     * Get the filter name from the nested query
     * user_profile.last_name => last_name
     */
    private function getNestedSortByFilterValue($sortBy): string
    {
        return explode('.', $sortBy)[1];
    }

    /**
     * Construct the foreign key
     */
    private function constructForeignKey(string $parentTable): string
    {
        return Str::singular($parentTable).'_id';
    }

    /**
     * Check if the sortBy filter contains a nested relationship
     */
    private function checkIfSortByFilterIsNested($sortBy): bool
    {
        $hasDot = Str::contains($sortBy, '.');
        $containsTwoParts = count(explode('.', $sortBy)) === 2;

        return $hasDot && $containsTwoParts;
    }

    /**
     * Build the orderBy query for joined tables
     */
    private function buildNestedOrderByQuery(
        string $sortBy,
        Builder $builder,
        string $tableName,
        string $filterName
    ): Builder {
        $schemaService = resolve(DbSchemaInspector::class);

        $nestedSortBy = $this->getNestedSortByFilterValue($sortBy);
        $nestedTableName = $this->getNestedSortByFilterRelatedTable($sortBy);
        $columnExists = $schemaService->checkIfColumnExists($nestedTableName, $nestedSortBy);
        $nestedSortBy = $columnExists ? $nestedSortBy : "$tableName.id";

        return $builder->orderBy($nestedSortBy, request($filterName));
    }
}
