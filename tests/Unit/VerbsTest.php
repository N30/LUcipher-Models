<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Events\Created;
use All1\LuModels\Events\Deleted;
use All1\LuModels\Events\Updated;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Tests\Fixtures\Gadget;
use All1\LuModels\Tests\Fixtures\PlainWidget;
use All1\LuModels\Tests\Fixtures\UnsafeWidget;
use All1\LuModels\Tests\Fixtures\Widget;
use All1\LuModels\Tests\UnitTestCase;
use All1\LuModels\Verbs\Annexation;
use All1\LuModels\Verbs\Creation;
use All1\LuModels\Verbs\Deletion;
use All1\LuModels\Verbs\Revelation;
use All1\LuModels\Verbs\Updation;
use All1\LuModels\Verbs\Verb;
use App\Events\CRUD\GadgetCreated;
use Illuminate\Database\Capsule\Manager as Capsule;

class VerbsTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer();
        $this->bootDatabase();
    }

    private function row(int $id): ?object
    {
        return Capsule::table('widgets')->where('id', $id)->first();
    }

    private function seedWidget(): int
    {
        return Capsule::table('widgets')->insertGetId(['name' => 'A', 'is_admin' => 0]);
    }

    // §5.3 — the safety flags, by behaviour

    public function test_update_does_not_write_a_non_fillable_key_on_a_default_lu_model(): void
    {
        $id = $this->seedWidget();
        $service = new LuModels;

        (new Updation($service, Widget::find($id), ['name' => 'B', 'is_admin' => 1], 'web'))->handle();

        $this->assertSame('B', $this->row($id)->name);
        $this->assertEquals(0, $this->row($id)->is_admin, 'is_admin is not in $fillable and must not be written');
    }

    public function test_update_fails_closed_on_a_model_that_declares_no_safety_flag(): void
    {
        $id = $this->seedWidget();

        (new Updation(new LuModels, PlainWidget::find($id), ['name' => 'B', 'is_admin' => 1], 'api'))->handle();

        $this->assertSame('B', $this->row($id)->name);
        $this->assertEquals(0, $this->row($id)->is_admin);
    }

    public function test_update_writes_every_key_only_when_the_model_explicitly_opts_out(): void
    {
        $id = $this->seedWidget();

        (new Updation(new LuModels, UnsafeWidget::find($id), ['name' => 'B', 'is_admin' => 1], 'web'))->handle();

        $this->assertEquals(1, $this->row($id)->is_admin);
    }

    public function test_create_does_not_write_a_non_fillable_key(): void
    {
        $service = new LuModels;
        (new Creation($service, new Widget, ['name' => 'C', 'is_admin' => 1], 'web'))->handle();

        $id = $service->model->id;
        $this->assertNotNull($id);
        $this->assertSame('C', $this->row($id)->name);
        $this->assertEquals(0, $this->row($id)->is_admin);
    }

    public function test_create_fails_closed_on_a_model_that_declares_no_safety_flag(): void
    {
        $service = new LuModels;
        (new Creation($service, new PlainWidget, ['name' => 'C', 'is_admin' => 1], 'web'))->handle();

        $this->assertEquals(0, $this->row($service->model->id)->is_admin);
    }

    // §5.5 — one base, $service always assigned, handle() returns the service

    public function test_every_verb_extends_the_base_and_assigns_the_service(): void
    {
        $service = new LuModels;
        foreach ([Creation::class, Revelation::class, Updation::class, Deletion::class, Annexation::class] as $class) {
            $verb = new $class($service, new Widget, ['x' => 1], 'rest');
            $this->assertInstanceOf(Verb::class, $verb, $class);
            $this->assertSame($service, $verb->service, $class);
            $this->assertSame(['x' => 1], $verb->data, $class);
            $this->assertSame('rest', $verb->controller_type, $class);
        }
    }

    public function test_delete_completes_and_returns_the_service(): void
    {
        $id = $this->seedWidget();
        $service = new LuModels;
        $model = Widget::find($id);

        $result = (new Deletion($service, $model, [], 'web'))->handle();

        $this->assertSame($service, $result);
        $this->assertSame($model, $service->model);
        $this->assertNull($this->row($id));
        $this->assertContains(Deleted::class, array_map('get_class', $this->firedObjectEvents));
    }

    public function test_update_returns_the_service_not_the_model(): void
    {
        $id = $this->seedWidget();
        $service = new LuModels;
        $model = Widget::find($id);

        $result = (new Updation($service, $model, ['name' => 'B'], 'web'))->handle();

        $this->assertSame($service, $result);
        $this->assertSame($model, $service->model);
        $this->assertContains(Updated::class, array_map('get_class', $this->firedObjectEvents));
    }

    public function test_create_returns_the_service_holding_the_persisted_model(): void
    {
        $service = new LuModels;

        $result = (new Creation($service, new Widget, ['name' => 'C'], 'web'))->handle();

        $this->assertSame($service, $result);
        $this->assertTrue($service->model->exists);
    }

    public function test_index_and_read_return_the_service(): void
    {
        $service = new class extends LuModels
        {
            public array $called = [];

            public function controller_index($model)
            {
                $this->called[] = 'index';

                return collect();
            }

            public function controller_show($model)
            {
                $this->called[] = 'show';

                return collect();
            }
        };

        $this->assertSame($service, (new Annexation($service, new Widget, [], 'web'))->handle());
        $this->assertSame($service, (new Revelation($service, new Widget, [], 'web'))->handle());
        $this->assertSame(['index', 'show'], $service->called);
    }

    // §5.5 — the Event override is looked up by the MODEL's name

    public function test_event_override_is_named_after_the_model_not_the_verb(): void
    {
        require_once __DIR__.'/../Fixtures/app-overrides.php';

        $this->assertSame('\\App\\Events\\CRUD\\GadgetCreated', Verb::appEventClass(new Gadget, 'Created'));
        $this->assertNull(Verb::appEventClass(new Widget, 'Created'));

        (new Creation(new LuModels, new Gadget, ['name' => 'G'], 'web'))->handle();

        $fired = array_map('get_class', $this->firedObjectEvents);
        $this->assertContains(GadgetCreated::class, $fired);
        $this->assertNotContains(Created::class, $fired);
    }

    public function test_without_an_override_the_package_event_fires(): void
    {
        (new Creation(new LuModels, new Widget, ['name' => 'W'], 'web'))->handle();

        $this->assertContains(Created::class, array_map('get_class', $this->firedObjectEvents));
    }
}
