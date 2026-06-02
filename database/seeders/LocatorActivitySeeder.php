<?php

namespace Database\Seeders;

use App\Models\Libraries\LocatorActivity;
use Carbon\Carbon;

class LocatorActivitySeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/locator_activities.json'));
        $nodesJson = json_decode($rawData, true);

        $this->seedNodes($nodesJson);
    }

    /**
     * Recursively parse and insert nodes into the database.
     */
    protected function seedNodes(array $nodes, ?int $parentId = null): void
    {
        foreach ($nodes as $node) {
            $insertedNode = LocatorActivity::create([
                'key' => $node['key'],
                'label' => $node['label'],
                'data' => $node['data'] ?? $node['label'],
                'selectable' => $node['selectable'] ?? true,
                'parent_id' => $parentId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (! empty($node['children'])) {
                $this->seedNodes($node['children'], $insertedNode->id);
            }
        }
    }

    /**
     * Get table name via model definition.
     */
    protected function tableName(): string
    {
        return app(LocatorActivity::class)->getTable();
    }

    /** * Check if table needs execution hooks.
     */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
