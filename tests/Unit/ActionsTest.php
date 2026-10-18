<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Actions\Action;
use All1\LuModels\Actions\Create;
use All1\LuModels\Actions\Delete;
use All1\LuModels\Actions\Index;
use All1\LuModels\Actions\Read;
use All1\LuModels\Actions\Update;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Tests\Fixtures\Gadget;
use All1\LuModels\Tests\Fixtures\Widget;
use All1\LuModels\Tests\UnitTestCase;
use All1\LuModels\Verbs\Creation;
use All1\LuModels\Verbs\Verb;
use App\Models\Verbs\CRUD\GadgetUpdation;
use Illuminate\Database\Capsule\Manager as Capsule;

/** Phase 4 §1: one class per operation; the Verbs are adapters over it. */
class ActionsTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer(['lu::models' => ['public_create_models' => [Gadget::class], 'public_update_models' => [Gadget::class]]]);
        $this->bootDatabase();
        require_once __DIR__.'/../Fixtures/app-overrides.php';
    }

    public function test_each_operation_knows_its_policy_name_and_legacy_verb(): void
    {
        $expected = [
            Index::class => ['index', 'Annexation'],
            Read::class => ['show', 'Revelation'],
            Create::class => ['create', 'Creation'],
            Update::class => ['update', 'Updation'],
            Delete::class => ['delete', 'Deletion'],
        ];
        foreach ($expected as $class => [$name, $verb]) {
            $this->assertInstanceOf(Action::class, new $class);
            $this->assertSame($name, (new $class)->name(), $class);
            $this->assertSame($verb, $class::VERB, $class);
        }
    }

    public function test_run_is_a_static_shim_that_authorizes_then_performs(): void
    {
        $service = new LuModels;

        $result = Create::run($service, new Gadget, ['name' => 'G'], 'api');

        $this->assertSame($service, $result);
        $this->assertTrue($service->model->exists);
        $this->assertSame('G', Capsule::table('widgets')->sole()->name);
        $this->assertSame(['gadget.created'], array_values(array_filter($this->stringEventNames(), fn ($n) => str_starts_with($n, 'gadget.'))));
    }

    public function test_the_legacy_verb_map_routes_each_name_to_its_operation(): void
    {
        $this->assertSame(Create::class, LuModels::actionForVerb('Creation'));
        $this->assertSame(Update::class, LuModels::actionForVerb('Updation'));
        $this->assertSame(Delete::class, LuModels::actionForVerb('Deletion'));
        $this->assertSame(Read::class, LuModels::actionForVerb('Revelation'));
        $this->assertSame(Index::class, LuModels::actionForVerb('Annexation'));
    }

    public function test_a_legacy_verb_override_at_the_old_address_still_runs(): void
    {
        $id = Capsule::table('widgets')->insertGetId(['name' => 'A', 'is_admin' => 0]);
        GadgetUpdation::$calls = 0;

        $service = (new Update)->execute(new LuModels, Gadget::find($id), ['name' => 'B'], 'web');

        $this->assertSame(1, GadgetUpdation::$calls);
        $this->assertSame('via legacy verb', Capsule::table('widgets')->where('id', $id)->sole()->name);
        $this->assertContains('gadget.updated', $this->stringEventNames(), 'the string event still fires around the override');
        $this->assertSame('via legacy verb', $service->model->name);
    }

    public function test_the_package_verbs_are_adapters_over_perform(): void
    {
        $service = new LuModels;
        $verb = new Creation($service, new Widget, ['name' => 'W'], 'rest');

        $this->assertInstanceOf(Verb::class, $verb);
        $this->assertSame($service, $verb->handle());
        $this->assertTrue($verb->model->exists, 'the adapter exposes the record the action wrote');
        $this->assertSame([], array_filter($this->stringEventNames(), fn ($n) => str_starts_with($n, 'widget.')), 'a Verb alone fires no string event, as in 3.0.x');
    }

    public function test_an_app_subclass_overrides_perform_only(): void
    {
        $custom = new class extends Create
        {
            public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
            {
                $data['name'] = strtoupper($data['name']);

                return parent::perform($service, $model, $data, $controller_type);
            }
        };

        $service = $custom->handle(new LuModels, new Gadget, ['name' => 'quiet'], 'api');

        $this->assertSame('QUIET', $service->model->name);
    }
}
