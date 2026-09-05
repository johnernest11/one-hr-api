<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ItemsSeeder extends CiCdCompliantSeeder
{
    /**
     * Chunk size to keep batch inserts within safe SQL query limits.
     */
    private const BATCH_SIZE = 500;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('database/seeders/dumps/items.json');

        $itemsData = json_decode(File::get($filePath), true) ?? [];

        $now = Carbon::now();

        // Get array of valid enum string values
        $validStatuses = array_column(EmploymentStatus::cases(), 'value');

        // Attach timestamps & sanitize employment_status to match MySQL ENUM
        $items = array_map(function ($item) use ($now, $validStatuses) {
            $item['created_at'] = $now;
            $item['updated_at'] = $now;

            $statusRaw = trim((string) ($item['employment_status'] ?? ''));

            // Check if status is valid; if not, match pattern or default to Contract of Service
            if (!in_array($statusRaw, $validStatuses, true)) {
                $statusLower = strtolower($statusRaw);

                if (str_contains($statusLower, 'perm') || str_contains($statusLower, 'regular')) {
                    $item['employment_status'] = EmploymentStatus::PERMANENT->value;
                } elseif (str_contains($statusLower, 'contractual')) {
                    $item['employment_status'] = EmploymentStatus::CONTRACTUAL->value;
                } elseif (str_contains($statusLower, 'casual')) {
                    $item['employment_status'] = EmploymentStatus::CASUAL->value;
                } elseif (str_contains($statusLower, 'temporary')) {
                    $item['employment_status'] = EmploymentStatus::TEMPORARY->value;
                } elseif (str_contains($statusLower, 'coterminous') || str_contains($statusLower, 'co-terminous')) {
                    $item['employment_status'] = EmploymentStatus::COTERMINOUS->value;
                } elseif (str_contains($statusLower, 'job order') || str_contains($statusLower, 'jo')) {
                    $item['employment_status'] = EmploymentStatus::JOB_ORDER->value;
                } elseif (str_contains($statusLower, 'probationary')) {
                    $item['employment_status'] = EmploymentStatus::PROBATIONARY->value;
                } else {
                    $item['employment_status'] = EmploymentStatus::CONTRACT_OF_SERVICE->value;
                }
            }

            return $item;
        }, $itemsData);

        // Run batch inserts within a single database transaction
        DB::transaction(function () use ($items) {
            foreach (array_chunk($items, self::BATCH_SIZE) as $chunk) {
                try {
                    Item::insert($chunk);
                } catch (\Throwable $e) {
                    dd($e->getMessage());
                }
            }
        });
    }

    protected function tableName(): string
    {
        return app(Item::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}