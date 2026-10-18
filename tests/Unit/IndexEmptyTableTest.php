<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Services\LuModels;
use All1\LuModels\Tests\Fixtures\Widget;
use All1\LuModels\Tests\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Request;

/** §3 — index on an empty table used to 500 on $data[0]. */
class IndexEmptyTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer([], Request::create('/', 'GET', ['limit' => 10]));
        $this->bootDatabase();
    }

    public function test_index_on_an_empty_table_returns_an_empty_result_with_the_table_columns(): void
    {
        $service = new LuModels;

        $data = $service->controller_index(new Widget);

        $this->assertCount(0, $data);
        $this->assertSame($data, $service->data);
        $this->assertSame(['id', 'name', 'is_admin'], $service->db_columns);
        $this->assertArrayHasKey('search', $service->filters);
    }

    public function test_index_with_rows_still_reads_columns_from_the_first_row(): void
    {
        Capsule::table('widgets')->insert(['name' => 'A', 'is_admin' => 0]);
        $service = new LuModels;

        $data = $service->controller_index(new Widget);

        $this->assertCount(1, $data);
        $this->assertSame(['id', 'name', 'is_admin'], $service->db_columns);
    }
}
