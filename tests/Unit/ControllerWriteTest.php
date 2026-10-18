<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use All1\LuModels\Http\Controllers\ResourceController;
use All1\LuModels\Routing\ResourceRoutes;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Tests\Fixtures\Widget;
use All1\LuModels\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

/**
 * The write actions driven through the controllers, past guardMethod: the web transport must
 * redirect after update and destroy (3.0.1's first cut raised "Undefined property
 * LuModels::$id" at ResourceController.php:58, a 500), and the API keeps 3.0.0's 'data' shape.
 *
 * Warnings are turned into exceptions, as Laravel's HandleExceptions does, so an undefined
 * property fails the test instead of passing silently.
 */
class ControllerWriteTest extends TestCase
{
    private Router $router;

    private Store $session;

    protected function setUp(): void
    {
        parent::setUp();

        set_error_handler(function (int $level, string $message, string $file = '', int $line = 0) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        });

        $app = $this->bootContainer(['lu::models' => [
            'after_store_redirect' => 'show',
            'after_update_redirect' => 'show',
            'after_destroy_redirect' => 'index',
            'public_update_models' => [Widget::class],
            'public_delete_models' => [Widget::class],
        ]]);
        $this->bootDatabase();

        $this->router = new Router($app->make('events'), $app);
        foreach (['web' => [ResourceController::class, 'lu::web.resources', true], '/api' => [ApiResourceController::class, 'lu::api.resources', false]] as $uri => [$controller, $name, $optional]) {
            ResourceRoutes::register($uri, $controller, $name, $optional, $this->router);
        }
        $this->router->getRoutes()->refreshNameLookups();

        $this->session = new Store('test', new ArraySessionHandler(10));
        $app->instance('session', $this->session);
        $app->instance('auth', new class
        {
            public function check(): bool
            {
                return false;
            }
        });

        Widget::query()->insert(['id' => 1, 'name' => 'Old', 'is_admin' => 0]);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    /** Route $method $uri the way the kernel would, and build the controller for it. */
    private function dispatch(string $method, string $uri, array $input = []): mixed
    {
        $request = Request::create('http://localhost'.$uri, $method, $input);
        $route = $this->router->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        $url = new UrlGenerator($this->router->getRoutes(), $request);
        $redirector = new Redirector($url);
        $redirector->setSession($this->session);
        $this->app->instance('url', $url);
        $this->app->instance('redirect', $redirector);

        // The constructor reads the table with MySQL's SHOW FULL COLUMNS; set its results directly.
        $class = $route->getControllerClass();
        $controller = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $controller->service = new LuModels;
        $controller->model = new Widget;
        $controller->action = $route->parameter('action') ?? 'index';
        $controller->controller_type = explode('.', substr($route->getName(), strlen('lu::')))[0];
        $controller->route_prefix = 'widgets';
        $controller->model_name = 'Widget';
        $controller->columns = [];

        return $controller($request, $route->parameter('model'), $route->parameter('action'), $route->parameter('id'));
    }

    public function test_web_update_writes_the_row_and_redirects_to_show(): void
    {
        $response = $this->dispatch('PUT', '/web/widgets/update/1', ['name' => 'New', 'is_admin' => 1]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/web/widgets/show/1', $response->getTargetUrl());
        $this->assertSame(['Record updated'], $this->session->get('messages'));
        $row = Widget::find(1);
        $this->assertSame('New', $row->name);
        $this->assertSame(0, (int) $row->is_admin, 'is_admin is not fillable and must not be written');
    }

    public function test_web_patch_update_redirects_too(): void
    {
        $response = $this->dispatch('PATCH', '/web/widgets/update/1', ['name' => 'Patched']);

        $this->assertSame('http://localhost/web/widgets/show/1', $response->getTargetUrl());
        $this->assertSame('Patched', Widget::find(1)->name);
    }

    public function test_web_destroy_deletes_the_row_and_redirects_to_index(): void
    {
        $response = $this->dispatch('DELETE', '/web/widgets/destroy/1');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/web/widgets/index/1', $response->getTargetUrl());
        $this->assertSame(['Record deleted'], $this->session->get('messages'));
        $this->assertNull(Widget::find(1));
    }

    public function test_web_update_of_a_missing_record_redirects_instead_of_failing(): void
    {
        $response = $this->dispatch('PUT', '/web/widgets/update/999', ['name' => 'Nope']);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/web/widgets/show', $response->getTargetUrl());
        $this->assertSame(['Record not found'], $this->session->get('messages'));
        $this->assertSame('Old', Widget::find(1)->name);
    }

    public function test_api_update_returns_the_model_in_data_as_in_3_0_0(): void
    {
        $result = $this->dispatch('PUT', '/api/widgets/update/1', ['name' => 'Api', 'is_admin' => 1]);

        $this->assertSame('Record updated', $result['message']);
        $this->assertInstanceOf(Widget::class, $result['data']);
        $this->assertSame(1, $result['data']->id);
        $this->assertSame('Api', $result['data']->name);
        $this->assertSame(0, (int) Widget::find(1)->is_admin);
    }

    public function test_api_destroy_returns_the_deleted_model_in_data(): void
    {
        $result = $this->dispatch('DELETE', '/api/widgets/destroy/1');

        $this->assertSame('Record deleted', $result['message']);
        $this->assertInstanceOf(Widget::class, $result['data']);
        $this->assertSame(1, $result['data']->id);
        $this->assertFalse($result['data']->exists);
        $this->assertNull(Widget::find(1));
    }

    public function test_record_of_accepts_the_service_a_model_or_neither(): void
    {
        $widget = new Widget(['name' => 'x']);
        $service = new LuModels;
        $service->model = $widget;

        $this->assertSame($widget, ApiResourceController::recordOf($service));
        $this->assertSame($widget, ApiResourceController::recordOf($widget), 'an app override may return the model itself');
        $this->assertSame($widget, ApiResourceController::recordOf([], $widget));
        $this->assertNull(ApiResourceController::recordOf([]));
    }
}
