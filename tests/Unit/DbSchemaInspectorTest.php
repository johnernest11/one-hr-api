<?php

namespace Tests\Unit;

use App\Services\DbSchemaInspector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DbSchemaInspectorTest extends TestCase
{
    private DbSchemaInspector $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DbSchemaInspector();

        // create dummy table
        Schema::create('stubs', function (Blueprint $table) {
            $table->id();
            $table->integer('col_2');
            $table->integer('col_3');
            $table->integer('col_4');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('stubs');
        parent::tearDown();
    }

    public function test_it_can_get_all_table_columns(): void
    {
        $expectedColumns = ['id', 'col_2', 'col_3', 'col_4'];
        $result = $this->service->getAllColumns('stubs');

        $this->assertTrue($this->arraysHaveSameValue($expectedColumns, $result));
    }

    public function test_it_can_check_if_column_exists(): void
    {
        $result = $this->service->checkIfColumnExists('stubs', 'col_2');
        $this->assertTrue($result);
    }
}
