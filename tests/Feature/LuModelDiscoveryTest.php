<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Events\Index as IndexEvent;
use All1\LuModels\Events\Read as ReadEvent;
use All1\LuModels\Models\LuModel;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Tests\TestCase;
use App\Models\Memo;
use App\Models\Note;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

/** Discovery and the LuModel helpers the 3.0.x consumers call. */
class LuModelDiscoveryTest extends TestCase
{
    public function test_discovery_sorts_lu_models_from_plain_ones_and_survives_a_missing_folder(): void
    {
        $this->assertArrayHasKey(Post::class, LuModel::getLuModels());
        $this->assertArrayNotHasKey(Note::class, LuModel::getLuModels());
        $this->assertArrayHasKey(Note::class, LuModel::getNonLuModels());
        $this->assertArrayHasKey(Memo::class, LuModel::getNonLuModels());
        $this->assertArrayNotHasKey(Post::class, LuModel::getNonLuModels());

        $this->assertTrue(LuModel::getMetaData(LuModel::class)['abstract'], 'the base class is not a model to serve');

        config(['lu::models.models_path' => '/nowhere/at/all']);
        $this->assertSame([], LuModel::getModels());
    }

    public function test_the_instance_delegate_and_the_deprecated_read_events_still_work(): void
    {
        $user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        Post::create(['user_id' => $user->id, 'title' => 'Hello']);
        $this->actingAs($user);

        $service = (new Post)->fromData('index', new LuModels, [], 'api');
        $this->assertInstanceOf(LuModels::class, $service);
        $this->assertSame(1, $service->data->total());

        $this->assertSame([], (new IndexEvent(new Post, [], 'api'))->broadcastOn());
        $this->assertSame([], (new ReadEvent(new Post, [], 'api'))->broadcastOn());
    }

    public function test_lu_openapi_refuses_a_path_it_cannot_write(): void
    {
        $this->assertSame(1, Artisan::call('lu:openapi', ['path' => '/nowhere/at/all/openapi.json']));
        $this->assertStringContainsString('Could not write', Artisan::output());
    }
}
