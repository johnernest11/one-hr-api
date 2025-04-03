<?php

namespace Database\Seeders;

use App\Models\Libraries\Position;
use Carbon\Carbon;

class PositionsSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/positions.json'));
        $positionsJson = json_decode($rawData, true);

        $positions = [];
        foreach ($positionsJson as $position) {
            $positions[] = [
                'id' => $position['id'],
                'title' => $position['title'],
                'parenthetical_title' => $position['parenthetical_title'],
                'level' => $position['level'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        Position::insert($positions);
    }

    protected function tableName(): string
    {
        return app(Position::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
