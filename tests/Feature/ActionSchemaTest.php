<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Actions\Create;
use All1\LuModels\Actions\Delete;
use All1\LuModels\Actions\Index;
use All1\LuModels\Actions\Read;
use All1\LuModels\Actions\Update;
use All1\LuModels\Tests\TestCase;
use App\Models\Note;
use App\Models\Post;
use App\Models\User;

/** Phase 4 §8: an operation declares what it accepts and which models it applies to (study §5.13). */
class ActionSchemaTest extends TestCase
{
    public function test_create_describes_the_writable_fields_without_what_the_package_injects(): void
    {
        $schema = (new Create)->schema(Post::class);

        $this->assertSame('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(['title', 'body'], array_keys($schema['properties']), 'user_id is injected by the package, secret_token is not writable');
        $this->assertSame('string', $schema['properties']['title']['type']);
        $this->assertSame(120, $schema['properties']['title']['maxLength'], 'from the max:120 rule');
        $this->assertSame('Shown as the headline', $schema['properties']['title']['description']);
        $this->assertSame(['string', 'null'], $schema['properties']['body']['type']);
        $this->assertSame(['title'], $schema['required']);
    }

    public function test_update_requires_nothing_because_input_is_partial(): void
    {
        $schema = (new Update)->schema(Post::class);

        $this->assertSame(['title', 'body'], array_keys($schema['properties']));
        $this->assertSame([], $schema['required']);
    }

    public function test_read_and_delete_take_the_key(): void
    {
        foreach ([new Read, new Delete] as $action) {
            $schema = $action->schema(Post::class);
            $this->assertSame(['id'], array_keys($schema['properties']));
            $this->assertSame(['integer', 'string'], $schema['properties']['id']['type']);
            $this->assertSame(['id'], $schema['required']);
        }
    }

    public function test_index_describes_the_query_parameters_from_what_the_listing_may_read(): void
    {
        $schema = (new Index)->schema(Post::class);

        $this->assertSame(['asc', 'desc'], $schema['properties']['order']['enum']);
        $this->assertSame(200, $schema['properties']['per_page']['maximum']);
        $this->assertSame(['id', 'user_id', 'title', 'created_at', 'updated_at'], $schema['properties']['search_fields']['items']['enum'], 'body and secret_token are not searchable on the index');
        $this->assertSame(['user'], $schema['properties']['with']['items']['enum']);
        $this->assertSame(['user'], $schema['properties']['include']['items']['enum']);
        $this->assertSame(['id', 'user_id', 'title', 'created_at', 'updated_at'], array_keys($schema['properties']['filter']['properties']), 'only readable fields are filterable');
        $this->assertSame('integer', $schema['properties']['filter']['properties']['id']['anyOf'][0]['type']);
        $this->assertContains('-created_at', $schema['properties']['sort']['items']['enum']);
        $this->assertSame(['id', 'user_id', 'title', 'created_at', 'updated_at'], $schema['properties']['fields']['items']['enum']);
    }

    public function test_supports_follows_exposure(): void
    {
        $create = new Create;

        $this->assertTrue($create->supports(Post::class));
        $this->assertTrue($create->supports(Post::class, 'api'));
        $this->assertTrue($create->supports(new Note, 'api'));
        $this->assertFalse($create->supports(Note::class, 'web'));
        $this->assertFalse($create->supports(User::class), 'User is exposed nowhere');
    }
}
