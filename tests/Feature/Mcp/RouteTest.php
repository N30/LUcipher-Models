<?php

namespace All1\LuModels\Tests\Feature\Mcp;

use All1\LuModels\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** The MCP server sits at lu::models.mcp.path with the API's own middleware. */
class RouteTest extends TestCase
{
    public function test_the_server_is_routed_with_the_mcp_rate_limit(): void
    {
        $route = Route::getRoutes()->match(Request::create('/mcp/lu-models', 'POST', server: ['CONTENT_TYPE' => 'application/json']));

        $this->assertContains('throttle:lu-models-mcp', $route->middleware());
        $this->assertContains('api', $route->middleware(), 'the test app has no sanctum and opts in to unauthenticated API routes');
        $this->assertSame(405, $this->get('/mcp/lu-models')->getStatusCode(), 'GET is not a transport method');
    }
}
