<?php

namespace All1\LuModels\Tests\Unit;

use All1\LuModels\Http\Controllers\Api\ApiResourceController;
use All1\LuModels\Http\Controllers\Api\ApiRestController;
use All1\LuModels\Http\Controllers\ResourceController;
use All1\LuModels\Routing\ResourceRoutes;
use All1\LuModels\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** §5.4 — the shipped route file, method-scoped. */
class RoutesTest extends TestCase
{
    private Router $router;

    private function loadRoutes(bool $sanctum = true, bool $allowUnauthenticatedApi = false): void
    {
        $app = $this->bootContainer(['lu::models' => [
            'enable_web_routes' => true,
            'enable_rest_routes' => true,
            'enable_api_routes' => true,
            'enable_spa_routes' => false,
            'allow_unauthenticated_api' => $allowUnauthenticatedApi,
            'web_middleware' => ['web', 'auth'],
        ]]);
        $app->instance('log', new class
        {
            public array $warnings = [];

            public function warning($message): void
            {
                $this->warnings[] = $message;
            }
        });

        $this->router = new Router($app->make('events'), $app);
        $app->instance('router', $this->router);
        $app->instance(Router::class, $this->router);
        if ($sanctum) {
            $this->router->aliasMiddleware('auth:sanctum', \stdClass::class);
        }

        require __DIR__.'/../../routes/lu_models-routes.php';
        $this->router->getRoutes()->refreshNameLookups();
    }

    private function match(string $method, string $uri): Route
    {
        return $this->router->getRoutes()->match(Request::create($uri, $method));
    }

    public static function transports(): array
    {
        return [
            'web' => ['/web', 'lu::web.resources', ResourceController::class],
            'rest' => ['/rest', 'lu::rest.resources', ApiRestController::class],
            'api' => ['/api', 'lu::api.resources', ApiResourceController::class],
        ];
    }

    #[DataProvider('transports')]
    public function test_get_cannot_reach_destroy_store_or_update(string $prefix): void
    {
        $this->loadRoutes();

        foreach (['/widgets/destroy/5', '/widgets/store', '/widgets/update/5'] as $path) {
            try {
                $this->match('GET', $prefix.$path);
                $this->fail("GET {$prefix}{$path} must not match a route");
            } catch (MethodNotAllowedHttpException $e) {
                $this->assertStringNotContainsString('GET', $e->getHeaders()['Allow']);
            }
        }
    }

    #[DataProvider('transports')]
    public function test_each_action_is_reached_only_by_its_method(string $prefix, string $name, string $controller): void
    {
        $this->loadRoutes();

        $cases = [
            ['GET', '/widgets/index', $name, 'index'],
            ['GET', '/widgets/create', $name, 'create'],
            ['GET', '/widgets/show/5', $name, 'show'],
            ['GET', '/widgets/edit/5', $name, 'edit'],
            ['HEAD', '/widgets/show/5', $name, 'show'],
            ['POST', '/widgets/store', $name.'.store', 'store'],
            ['PUT', '/widgets/update/5', $name.'.update', 'update'],
            ['PATCH', '/widgets/update/5', $name.'.update', 'update'],
            ['DELETE', '/widgets/destroy/5', $name.'.destroy', 'destroy'],
        ];
        foreach ($cases as [$method, $path, $routeName, $action]) {
            $route = $this->match($method, $prefix.$path);
            $this->assertSame($routeName, $route->getName(), "$method $prefix$path");
            $this->assertSame($action, $route->parameter('action'));
            $this->assertSame($controller, $route->getActionName());
        }

        foreach ([['POST', '/widgets/destroy/5'], ['DELETE', '/widgets/update/5'], ['PUT', '/widgets/store'], ['POST', '/widgets/show/5']] as [$method, $path]) {
            try {
                $this->match($method, $prefix.$path);
                $this->fail("$method $prefix$path must not match a route");
            } catch (MethodNotAllowedHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[DataProvider('transports')]
    public function test_unknown_actions_are_not_routable(string $prefix): void
    {
        $this->loadRoutes();

        $this->expectException(NotFoundHttpException::class);
        $this->match('GET', $prefix.'/widgets/chooseView/5');
    }

    public function test_web_and_rest_default_to_index_without_an_action(): void
    {
        $this->loadRoutes();

        $this->assertSame('lu::web.resources', $this->match('GET', '/web/widgets')->getName());
        $this->assertSame('lu::rest.resources', $this->match('GET', '/rest/widgets')->getName());
    }

    public function test_existing_route_name_still_generates_every_action_url(): void
    {
        $this->loadRoutes();
        $url = new UrlGenerator($this->router->getRoutes(), Request::create('http://app.test/'));

        $this->assertSame('http://app.test/web/widgets/destroy/5', $url->route('lu::web.resources', ['widgets', 'destroy', 5]));
        $this->assertSame('http://app.test/web/widgets/update/5', $url->route('lu::web.resources', ['widgets', 'update', 5]));
        $this->assertSame('http://app.test/api/widgets/store', $url->route('lu::api.resources', ['widgets', 'store']));
    }

    public function test_api_routes_still_fail_closed_without_sanctum(): void
    {
        $this->loadRoutes(sanctum: false);

        $this->assertNull($this->router->getRoutes()->getByName('lu::api.resources'));
        $this->assertNull($this->router->getRoutes()->getByName('lu::api.resources.destroy'));
        $this->assertNotNull($this->router->getRoutes()->getByName('lu::web.resources.destroy'));
    }

    public function test_api_routes_registered_unauthenticated_only_on_explicit_opt_in(): void
    {
        $this->loadRoutes(sanctum: false, allowUnauthenticatedApi: true);

        $this->assertSame('lu::api.resources.destroy', $this->match('DELETE', '/api/widgets/destroy/5')->getName());
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->match('GET', '/api/widgets/destroy/5');
    }

    public function test_api_routes_carry_sanctum(): void
    {
        $this->loadRoutes();

        foreach (['lu::api.resources', 'lu::api.resources.store', 'lu::api.resources.update', 'lu::api.resources.destroy'] as $name) {
            $this->assertContains('auth:sanctum', $this->router->getRoutes()->getByName($name)->middleware(), $name);
        }
    }

    public function test_the_controller_guard_allows_only_the_matching_method(): void
    {
        $this->assertTrue(ResourceRoutes::allows('GET', 'index'));
        $this->assertTrue(ResourceRoutes::allows('GET', null));
        $this->assertTrue(ResourceRoutes::allows('HEAD', 'show'));
        $this->assertTrue(ResourceRoutes::allows('POST', 'store'));
        $this->assertTrue(ResourceRoutes::allows('put', 'update'));
        $this->assertTrue(ResourceRoutes::allows('PATCH', 'update'));
        $this->assertTrue(ResourceRoutes::allows('DELETE', 'destroy'));

        $this->assertFalse(ResourceRoutes::allows('GET', 'destroy'));
        $this->assertFalse(ResourceRoutes::allows('GET', 'store'));
        $this->assertFalse(ResourceRoutes::allows('GET', 'update'));
        $this->assertFalse(ResourceRoutes::allows('POST', 'destroy'));
        $this->assertFalse(ResourceRoutes::allows('GET', 'chooseView'));
        $this->assertFalse(ResourceRoutes::allows('GET', '__construct'));
    }
}
