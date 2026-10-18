<?php

namespace All1\LuModels\Tests\Feature\Api;

use All1\LuModels\Support\OpenApi;
use All1\LuModels\Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/** Phase 5 §2: the OpenAPI 3.1 document is built from the same descriptors and schemas the API runs on. */
class OpenApiTest extends TestCase
{
    public function test_the_document_describes_the_exposed_models_and_only_them(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $doc = $this->actingAs($user)->getJson('/api/openapi.json')->assertOk()->json();

        $this->assertSame('3.1.0', $doc['openapi']);
        $this->assertSame(OpenApi::DIALECT, $doc['jsonSchemaDialect']);
        $this->assertSame(url('/api'), $doc['servers'][0]['url']);
        $this->assertSame([['sanctum' => []]], $doc['security']);
        $this->assertSame('bearer', $doc['components']['securitySchemes']['sanctum']['scheme']);

        $this->assertArrayHasKey('/posts', $doc['paths']);
        $this->assertArrayHasKey('/posts/{id}', $doc['paths']);
        $this->assertArrayHasKey('/notes', $doc['paths'], 'Note exposes itself on api by declaration');
        $this->assertArrayNotHasKey('/memos', $doc['paths'], 'Memo is exposed on no transport');
        $this->assertSame(['get', 'post'], array_keys($doc['paths']['/posts']));
        $this->assertSame(['parameters', 'get', 'patch', 'put', 'delete'], array_keys($doc['paths']['/posts/{id}']));
        $this->assertSame('listPosts', $doc['paths']['/posts']['get']['operationId']);
        $this->assertSame('#/components/schemas/PostCreate', $doc['paths']['/posts']['post']['requestBody']['content']['application/json']['schema']['$ref']);
    }

    public function test_the_schemas_come_from_the_descriptor_and_the_operations(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $schemas = $this->actingAs($user)->getJson('/api/openapi.json')->json('components.schemas');

        $this->assertSame(120, $schemas['Post']['properties']['title']['maxLength'], 'from the max:120 rule and the column');
        $this->assertSame('Shown as the headline', $schemas['Post']['properties']['title']['description']);
        $this->assertArrayNotHasKey('secret_token', $schemas['Post']['properties'], 'hidden never reads out');
        $this->assertArrayHasKey('body', $schemas['Post']['properties']);
        $this->assertArrayNotHasKey('body', $schemas['PostSummary']['properties'], 'body is not readable on the index');
        $this->assertSame(['title'], $schemas['PostCreate']['required']);
        $this->assertSame([], $schemas['PostUpdate']['required']);
        $this->assertSame(['title', 'body'], array_keys($schemas['PostCreate']['properties']), 'user_id is injected, never asked for');
        foreach (['Error', 'ValidationError', 'PageMeta', 'PageLinks', 'DryRun'] as $shared) {
            $this->assertArrayHasKey($shared, $schemas);
        }
    }

    public function test_the_listing_parameters_carry_the_query_grammar(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        $parameters = collect($this->actingAs($user)->getJson('/api/openapi.json')->json('paths./posts.get.parameters'))->keyBy('name');

        foreach (['page', 'per_page', 'limit', 'filter', 'sort', 'order', 'search', 'search_fields', 'fields', 'include'] as $name) {
            $this->assertTrue($parameters->has($name), $name);
            $this->assertSame('query', $parameters[$name]['in']);
        }
        $this->assertSame('deepObject', $parameters['filter']['style']);
        $this->assertSame(['id', 'user_id', 'title', 'created_at', 'updated_at'], array_keys($parameters['filter']['schema']['properties']));
        $this->assertSame('boolean', collect($this->actingAs($user)->getJson('/api/openapi.json')->json('paths./posts.post.parameters'))->firstWhere('name', 'dry_run')['schema']['type']);
    }

    public function test_every_documented_path_and_method_routes_to_the_api(): void
    {
        $doc = OpenApi::document();
        $routes = Route::getRoutes();

        foreach ($doc['paths'] as $path => $item) {
            foreach ($item as $method => $operation) {
                if ($method === 'parameters') {
                    continue;
                }
                $uri = $doc['servers'][0]['url'].str_replace('{id}', '1', $path);
                $route = $routes->match(Request::create($uri, strtoupper($method)));
                $this->assertStringStartsWith('lu::api.', (string) $route->getName(), "$method $path");
                $this->assertStringNotContainsString('resources', (string) $route->getName(), "$method $path routes to a REST-shaped route");
            }
        }
    }

    public function test_the_command_prints_or_writes_the_same_document(): void
    {
        Artisan::call('lu:openapi');
        $printed = json_decode(Artisan::output(), true);
        $this->assertSame('3.1.0', $printed['openapi']);
        $this->assertArrayHasKey('/posts', $printed['paths']);

        $path = storage_path('lu-models-openapi-test.json');
        try {
            Artisan::call('lu:openapi', ['path' => $path]);
            $this->assertStringContainsString('written to', Artisan::output());
            $this->assertSame($printed, json_decode((string) file_get_contents($path), true));
        } finally {
            @unlink($path);
        }
    }
}
