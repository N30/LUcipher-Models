<?php

namespace All1\LuModels\Tests\Feature\Security;

use All1\LuModels\Support\Exposure;
use All1\LuModels\Tests\TestCase;
use App\Models\Note;
use App\Models\Post;
use App\Models\User;

/** Phase 3 §1: nothing is exposed until a model says so. */
class ExposureTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
    }

    public function test_an_unexposed_model_is_a_404_on_every_transport_even_for_a_signed_in_user(): void
    {
        $this->actingAs($this->user);

        $this->get(route('lu::web.resources', ['model' => 'users', 'action' => 'index']))->assertNotFound();
        $this->get(route('lu::rest.resources', ['model' => 'users', 'action' => 'index']))->assertNotFound();
        $this->getJson(route('lu::api.resources', ['model' => 'users', 'action' => 'index']))->assertNotFound();
    }

    public function test_a_model_listed_in_the_config_is_exposed_on_the_listed_transports_only(): void
    {
        config(['lu::models.models' => [Post::class => ['web']]]);
        $this->actingAs($this->user);

        $this->get(route('lu::web.resources', ['model' => 'posts', 'action' => 'index']))->assertOk();
        $this->getJson(route('lu::api.resources', ['model' => 'posts', 'action' => 'index']))->assertNotFound();
        $this->assertSame(['web'], Exposure::transportsFor(Post::class));
    }

    public function test_a_model_can_expose_itself_by_declaration(): void
    {
        $this->actingAs($this->user);

        $this->assertSame(['api'], Exposure::transportsFor(Note::class));
        $this->getJson(route('lu::api.resources', ['model' => 'notes', 'action' => 'index']))->assertOk();
        $this->get(route('lu::web.resources', ['model' => 'notes', 'action' => 'index']))->assertNotFound();
    }

    public function test_config_and_declaration_are_a_union(): void
    {
        config(['lu::models.models' => [Note::class => ['web']]]);

        $this->assertSame(['web', 'api'], Exposure::transportsFor(new Note));
        $this->assertTrue(Exposure::isExposed(Note::class, 'web'));
        $this->assertFalse(Exposure::isExposed(Note::class, 'spa'));
    }

    public function test_expose_all_exposes_every_model_but_policies_still_apply(): void
    {
        config(['lu::models.expose_all' => true]);
        $this->actingAs($this->user);

        $this->get(route('lu::web.resources', ['model' => 'users', 'action' => 'index']))->assertForbidden();
    }

    public function test_exposed_models_lists_only_what_the_transport_may_serve(): void
    {
        $this->assertEqualsCanonicalizing([Post::class, Note::class], array_keys(Exposure::exposedModels('api')));
        $this->assertSame([Post::class], array_keys(Exposure::exposedModels('web')));
    }
}
