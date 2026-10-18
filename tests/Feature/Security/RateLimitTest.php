<?php

namespace All1\LuModels\Tests\Feature\Security;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/** Phase 3 §5: per-transport and per-write budgets, enforced by Laravel's throttle middleware. */
class RateLimitTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
    }

    public function test_the_api_transport_answers_429_past_its_budget(): void
    {
        config(['lu::models.rate_limits.api' => 2]);
        $url = route('lu::api.resources', ['model' => 'posts', 'action' => 'index']);

        $this->actingAs($this->user)->getJson($url)->assertOk();
        $this->actingAs($this->user)->getJson($url)->assertOk();
        $this->actingAs($this->user)->getJson($url)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_writes_have_their_own_budget_across_transports(): void
    {
        config(['lu::models.rate_limits.writes' => 1, 'lu::models.rate_limits.api' => 100]);

        $this->actingAs($this->user)->postJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'store']), ['title' => 'One'])->assertOk();
        $this->actingAs($this->user)->postJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'store']), ['title' => 'Two'])->assertStatus(429);
        $this->assertDatabaseCount('posts', 1);

        $this->actingAs($this->user)->getJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'index']))->assertOk('reads are not charged to the write budget');
    }

    public function test_a_null_limit_turns_the_limiter_off(): void
    {
        config(['lu::models.rate_limits.web' => null]);
        $url = route('lu::web.resources', ['model' => 'posts', 'action' => 'index']);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)->get($url)->assertOk();
        }
    }

    public function test_the_default_budgets_are_declared_per_transport(): void
    {
        $this->assertSame(['web' => 120, 'rest' => 120, 'api' => 60, 'spa' => 120, 'mcp' => 60, 'writes' => 20], config('lu::models.rate_limits'));
        $this->assertContains('throttle:lu-models-web', Route::getRoutes()->getByName('lu::web.resources')->middleware());
        $this->assertContains('throttle:lu-models-writes', Route::getRoutes()->getByName('lu::api.resources.store')->middleware());
        $this->assertNotContains('throttle:lu-models-writes', Route::getRoutes()->getByName('lu::api.resources')->middleware());
        $this->assertDatabaseCount('posts', 0);

        Post::create(['user_id' => $this->user->id, 'title' => 'x']);
        $this->assertDatabaseCount('posts', 1);
    }
}
