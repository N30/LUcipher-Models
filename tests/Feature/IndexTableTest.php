<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;
use All1\LuModels\View\Components\IndexTable;
use App\Extra\Event;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

/** The listing table's cells: dotted relationship columns, dates, booleans, arrays, and a LuModel's displayText(). */
class IndexTableTest extends TestCase
{
    public function test_dotted_columns_show_the_related_records_attribute(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        Post::create(['user_id' => $user->id, 'title' => 'Hello']);

        $model = new Post;
        $model->index_columns = ['id', 'title', 'user.name', 'user.missing_relation.x', 'created_at']; // a LuModel lists id and created_at unless it says otherwise
        $html = Blade::render(
            '<x-lu_models::index-table :data="$data" :model="$model" :dbColumns="$columns" type="web" routePrefix="posts" />',
            ['data' => Post::with('user')->get(), 'model' => $model, 'columns' => ['id', 'title', 'user.name', 'user.missing_relation.x', 'created_at']],
        );

        $this->assertStringContainsString('Neo', $html, 'user.name through the loaded relation');
        $this->assertStringContainsString('User Name', $html, 'the header');
        $this->assertStringContainsString('Hello', $html);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $html, 'created_at as a short date');
    }

    public function test_cell_text_renders_every_scalar_kind_and_a_lu_model_shows_arrays_as_json(): void
    {
        $table = new IndexTable([], [], 'events', 'web', new Event);

        $this->assertSame('', $table->cellText(null));
        $this->assertSame('✓', $table->cellText(true));
        $this->assertSame('—', $table->cellText(false));
        $this->assertSame('{"a":1}', $table->cellText(['a' => 1]));
        $this->assertSame('2026-10-19 14:30', $table->cellText(new \DateTimeImmutable('2026-10-19 14:30:00')));
        $this->assertSame('12', $table->cellText(12));

        $event = Event::create(['title' => 'Launch', 'meta' => ['venue' => 'Hall A']]);
        $this->assertSame('{"venue":"Hall A"}', $event->displayText('meta'));
        $this->assertSame('Launch', $event->displayText('title'));

        $model = new Event;
        $model->index_columns = ['id', 'title', 'meta'];
        $html = Blade::render(
            '<x-lu_models::index-table :data="$data" :model="$model" :dbColumns="$columns" type="web" routePrefix="events" />',
            ['data' => Event::all(), 'model' => $model, 'columns' => ['id', 'title', 'meta']],
        );
        $this->assertStringContainsString('Hall A', $html);
    }

    public function test_index_columns_narrow_the_table_to_readable_ones_and_an_empty_table_says_so(): void
    {
        $html = Blade::render(
            '<x-lu_models::index-table :data="$data" :model="$model" :dbColumns="$columns" type="web" routePrefix="posts" />',
            ['data' => Post::all(), 'model' => new Post, 'columns' => ['id', 'user_id', 'title', 'created_at', 'updated_at']],
        );

        $this->assertStringContainsString('Nothing here yet.', $html);
        $this->assertStringNotContainsString('User Id', $html, 'index_columns names id, title and created_at');
        $this->assertStringNotContainsString('Updated At', $html);
    }
}
