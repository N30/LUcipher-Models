<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Actions\Create;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Tests\Fixtures\Gadget;
use All1\LuModels\Tests\Fixtures\Widget;
use All1\LuModels\Tests\TestCase;

/** §5.2 — the Action override is one class name, checked and run. */
class ActionOverrideTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer();
        require_once __DIR__.'/../Fixtures/app-overrides.php';
        \App\LuModels\Actions\CRUD\CreateGadget::$calls = [];
    }

    public function test_without_an_override_the_package_action_is_used(): void
    {
        $this->assertSame('\\'.Create::class, LuModels::actionClass('create', new Widget));
    }

    public function test_the_override_that_is_checked_is_the_one_that_runs(): void
    {
        $this->assertFalse(class_exists('\\App\\Models\\Actions\\CreateGadget'), 'the app defines only the documented class');
        $this->assertSame('\\App\\LuModels\\Actions\\CRUD\\CreateGadget', LuModels::actionClass('create', new Gadget));

        $service = new LuModels;
        $model = new Gadget;
        $result = LuModels::fromData('create', $service, $model, ['name' => 'G'], 'api');

        $this->assertSame($service, $result);
        $this->assertCount(1, \App\LuModels\Actions\CRUD\CreateGadget::$calls);
        $this->assertSame([$model, ['name' => 'G'], 'api'], \App\LuModels\Actions\CRUD\CreateGadget::$calls[0]);
    }
}
