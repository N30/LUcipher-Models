<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Support\Descriptor;
use All1\LuModels\Tests\TestCase;
use App\Models\Note;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Phase 4 §3: one description of a model, built once. */
class DescriptorTest extends TestCase
{
    public function test_it_describes_the_table_the_keys_and_the_help_text(): void
    {
        $d = Descriptor::build(Post::class);

        $this->assertSame('posts', $d->table);
        $this->assertSame('id', $d->key);
        $this->assertSame('Post', $d->headline);
        $this->assertSame('posts', $d->slug);
        $this->assertTrue($d->extendsLuModel);
        $this->assertTrue($d->field('id')['primary']);
        $this->assertTrue($d->field('id')['auto_increment']);
        $this->assertFalse($d->field('id')['fillable']);
        $this->assertSame('Shown as the headline', $d->field('title')['help'], '$lu_help is the help text');
        $this->assertSame(
            DB::getDriverName() === 'sqlite' ? '' : 'Shown as the headline',
            $d->field('title')['comment'],
            'the column comment is kept as a second source (SQLite stores none)'
        );
        $this->assertFalse($d->field('title')['nullable']);
        $this->assertTrue($d->field('body')['nullable']);
        $this->assertSame('textarea', $d->field('body')['input']);
        $this->assertSame('number', $d->field('user_id')['input']);
    }

    public function test_it_knows_what_each_action_may_see_and_write(): void
    {
        $d = Descriptor::build(Post::class);

        $this->assertSame(['index', 'show', 'create', 'edit'], $d->field('title')['visible']);
        $this->assertSame(['create', 'edit'], $d->field('title')['writable']);
        $this->assertSame(['show', 'create', 'edit'], $d->field('body')['visible'], '$lu_fields keeps body off the index');
        $this->assertTrue($d->field('secret_token')['hidden']);
        $this->assertSame(['create', 'edit'], $d->field('secret_token')['visible'], '$hidden governs reads only');
        $this->assertSame([], $d->field('secret_token')['writable']);
        $this->assertSame(['id', 'user_id', 'title', 'created_at', 'updated_at'], array_keys($d->fieldsFor('index')));
        $this->assertSame(['user_id', 'title', 'body'], array_keys($d->fieldsFor('create')));
    }

    public function test_it_resolves_relationships_transports_policy_rules_and_overrides(): void
    {
        $d = Descriptor::build(Post::class);

        $this->assertSame(['user' => User::class], $d->relationships);
        $this->assertSame(['web', 'rest', 'api', 'spa'], $d->transports);
        $this->assertSame(PostPolicy::class, $d->policy);
        $this->assertSame(['title' => 'required|string|max:120'], $d->rules['create']);
        $this->assertSame('package', $d->overrides['create']['source']);
        $this->assertSame('All1\LuModels\Actions\Create', $d->overrides['create']['class']);
        $this->assertSame('package', $d->overrides['views']['source']);
        $this->assertSame('app', $d->overrides['policy']['source']);
        $this->assertSame(['id', 'title', 'created_at'], $d->options['index_columns']);
        $this->assertTrue($d->options['update_safety']);

        $note = Descriptor::build(Note::class);
        $this->assertFalse($note->extendsLuModel);
        $this->assertSame(['api'], $note->transports);
        $this->assertSame('missing', Descriptor::build(User::class)->overrides['policy']['source']);
    }

    public function test_for_caches_and_forget_and_flush_clear_it(): void
    {
        $this->assertFalse(Cache::has(Descriptor::cacheKey(Post::class)));

        $first = Descriptor::for(Post::class);
        $this->assertTrue(Cache::has(Descriptor::cacheKey(Post::class)));
        $this->assertEquals($first->toArray(), Descriptor::for(new Post)->toArray(), 'a second call reads the cache');
        $this->assertEquals($first->toArray(), Descriptor::fromArray($first->toArray())->toArray(), 'round-trips through its array form');

        Descriptor::forget(Post::class);
        $this->assertFalse(Cache::has(Descriptor::cacheKey(Post::class)));

        Descriptor::for(Post::class);
        Descriptor::for(Note::class);
        Descriptor::flush();
        $this->assertFalse(Cache::has(Descriptor::cacheKey(Post::class)));
        $this->assertFalse(Cache::has(Descriptor::cacheKey(Note::class)));
    }

    public function test_the_legacy_column_shape_matches_what_the_blades_read(): void
    {
        $columns = Descriptor::build(Post::class)->legacyColumns();
        $title = collect($columns)->firstWhere('Field', 'title');

        $this->assertSame('NO', $title['Null']);
        $this->assertTrue($title['required']);
        $this->assertSame('text', $title['input_type']);
        $this->assertStringContainsString('Shown as the headline', $title['description'], 'help text reaches the legacy description the forms show');
        $this->assertSame('PRI', collect($columns)->firstWhere('Field', 'id')['Key']);
    }
}
