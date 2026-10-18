<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Services\LuModels;
use All1\LuModels\Tests\Fixtures\Widget;
use All1\LuModels\Tests\TestCase;
use Illuminate\Http\Request;

/** §5.1 — fireVerbs() fires the action's own {model}.{past} string event. */
class ServiceEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer([], Request::create('/', 'GET', ['limit' => 10]));
        $this->bootDatabase();
    }

    /** @return list<string> the README-contract events fired (widget.*) */
    private function contractEvents(): array
    {
        return array_values(array_filter($this->stringEventNames(), fn ($n) => str_starts_with($n, 'widget.')));
    }

    public function test_create_fires_model_created_with_the_persisted_model(): void
    {
        $service = new LuModels;
        $service->fireVerbs('Creation', $service, new Widget, ['name' => 'C'], 'web');

        $this->assertSame(['widget.created'], $this->contractEvents());
        $payload = $this->firedStringEvents[array_key_last($this->firedStringEvents)][1];
        $this->assertTrue($payload['model']->exists);
        $this->assertSame(['name' => 'C'], $payload['data']);
        $this->assertSame('web', $payload['controller_type']);
    }

    public function test_update_fires_model_updated(): void
    {
        $service = new LuModels;
        $service->fireVerbs('Creation', $service, new Widget, ['name' => 'C'], 'web');
        $this->firedStringEvents = [];

        $service->fireVerbs('Updation', $service, Widget::first(), ['name' => 'D'], 'web');

        $this->assertSame(['widget.updated'], $this->contractEvents());
    }

    public function test_delete_fires_model_deleted(): void
    {
        $service = new LuModels;
        $service->fireVerbs('Creation', $service, new Widget, ['name' => 'C'], 'web');
        $this->firedStringEvents = [];

        $service->fireVerbs('Deletion', $service, Widget::first(), [], 'web');

        $this->assertSame(['widget.deleted'], $this->contractEvents());
    }

    public function test_index_and_read_fire_no_string_event(): void
    {
        $service = new LuModels;
        $service->fireVerbs('Creation', $service, new Widget, ['name' => 'C'], 'web');
        $this->firedStringEvents = [];

        $service->fireVerbs('Annexation', $service, new Widget, [], 'web');
        $service->fireVerbs('Revelation', $service, Widget::first(), [], 'web');

        $this->assertSame([], $this->contractEvents());
    }

    public function test_string_event_name_map(): void
    {
        $model = new Widget;
        $this->assertSame('widget.created', LuModels::stringEventName('Creation', $model));
        $this->assertSame('widget.updated', LuModels::stringEventName('Updation', $model));
        $this->assertSame('widget.deleted', LuModels::stringEventName('Deletion', $model));
        $this->assertSame('widget.created', LuModels::stringEventName('\\App\\Models\\Verbs\\CRUD\\Creation', $model));
        $this->assertNull(LuModels::stringEventName('Annexation', $model));
        $this->assertNull(LuModels::stringEventName('Revelation', $model));
    }
}
