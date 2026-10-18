<?php

namespace All1\LuModels\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class UnitTestCase extends BaseTestCase
{
    protected ?Container $app = null;

    /** @var list<array{0:string,1:array}> every string event dispatched, as [name, payload] */
    protected array $firedStringEvents = [];

    /** @var list<object> every object event dispatched */
    protected array $firedObjectEvents = [];

    /**
     * A minimal container standing in for the Laravel app: events, config, request and the
     * facade root. Records every dispatched event.
     */
    protected function bootContainer(array $config = [], ?Request $request = null): Container
    {
        $this->app = $app = new Container;
        Container::setInstance($app);
        $app->instance('app', $app);
        $app->instance(Container::class, $app);

        $events = new Dispatcher($app);
        $events->listen('*', function ($name, $payload) {
            if (is_object($payload[0] ?? null) && $payload[0] instanceof $name) {
                $this->firedObjectEvents[] = $payload[0];
            } else {
                $this->firedStringEvents[] = [$name, $payload];
            }
        });
        $app->instance('events', $events);
        $app->instance('config', new Repository($config));
        $app->instance('request', $request ?? Request::create('/', 'GET'));
        $translator = new Translator(new ArrayLoader, 'en');
        $app->instance('translator', $translator);
        $app->instance('validator', new Factory($translator, $app));

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        return $app;
    }

    /** An in-memory SQLite connection with a `widgets` table, wired into Eloquent. */
    protected function bootDatabase(): Capsule
    {
        $app = $this->app ?? $this->bootContainer();

        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setEventDispatcher($app->make('events'));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->schema()->create('widgets', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('is_admin')->default(0);
        });

        return $capsule;
    }

    /** @return list<string> */
    protected function stringEventNames(): array
    {
        return array_map(fn ($e) => $e[0], $this->firedStringEvents);
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();
        Model::unsetEventDispatcher();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        $this->app = null;
        $this->firedStringEvents = [];
        $this->firedObjectEvents = [];

        parent::tearDown();
    }
}
