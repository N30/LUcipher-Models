<?php

namespace All1\LuModels\Tests\Feature\Security;

use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;

/** Phase 3 §2: an exposed model still needs a policy; the super-admin bypass is explicit config. */
class PolicyRequiredTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Neo', 'email' => 'neo@example.test']);
        config(['lu::models.models' => [User::class => true, Post::class => true]]);
    }

    private function usersIndex(): string
    {
        return route('lu::web.resources', ['model' => 'users', 'action' => 'index']);
    }

    public function test_a_signed_in_user_is_denied_on_an_exposed_model_without_a_policy(): void
    {
        $response = $this->actingAs($this->user)->get($this->usersIndex());

        $response->assertForbidden();
        $this->assertStringContainsString('No policy is registered for App\Models\User', $response->exception->getMessage());
    }

    public function test_a_guest_is_denied_without_an_explicit_public_opt_in(): void
    {
        $this->get($this->usersIndex())->assertForbidden();
    }

    public function test_a_public_opt_in_lets_a_guest_read_without_a_policy(): void
    {
        config(['lu::models.public_index_models' => [User::class]]);

        $this->get($this->usersIndex())->assertOk();
    }

    public function test_the_configured_super_admin_method_bypasses_the_policy_requirement(): void
    {
        $this->user->is_super = true;

        $this->actingAs($this->user)->get($this->usersIndex())->assertOk();
    }

    public function test_the_bypass_can_be_turned_off(): void
    {
        $this->user->is_super = true;
        config(['lu::models.super_admin_method' => null]);

        $this->actingAs($this->user)->get($this->usersIndex())->assertForbidden();
    }

    public function test_a_model_with_a_policy_is_governed_by_it(): void
    {
        $this->actingAs($this->user)->get(route('lu::web.resources', ['model' => 'posts', 'action' => 'index']))->assertOk();
    }
}
