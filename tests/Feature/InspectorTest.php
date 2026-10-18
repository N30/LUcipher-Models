<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Support\Descriptor;
use All1\LuModels\Tests\Fixtures\Gadget;
use All1\LuModels\Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 4 §4: `lu:models` prints the resolution table (study §5.7, §6.6).
 * Output is captured whole: Laravel's expectsOutputToContain() matches one substring per written line.
 */
class InspectorTest extends TestCase
{
    private function inspect(array $arguments = []): array
    {
        $code = Artisan::call('lu:models', $arguments);

        return [$code, Artisan::output()];
    }

    public function test_it_lists_every_discovered_model_with_its_resolution(): void
    {
        [$code, $out] = $this->inspect();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('3 model(s)', $out);
        $this->assertStringContainsString(Post::class, $out);
        $this->assertStringContainsString('web, rest, api, spa', $out);
        $this->assertStringContainsString('App\Policies\PostPolicy', $out);
        $this->assertStringContainsString('all default', $out);
        $this->assertStringContainsString('— (not exposed)', $out, 'User is discovered but exposed nowhere');
        $this->assertStringContainsString('Transports enabled: web, rest, api, spa', $out);
    }

    public function test_an_exposed_model_without_a_policy_is_flagged(): void
    {
        config(['lu::models.models' => [Post::class => true, User::class => ['web']]]);

        [, $out] = $this->inspect();

        $this->assertStringContainsString('MISSING', $out);
        $this->assertStringContainsString('every signed-in user is denied', $out);
    }

    public function test_it_drills_into_one_model_by_class_basename_or_slug(): void
    {
        foreach ([Post::class, 'Post', 'posts'] as $wanted) {
            [$code, $out] = $this->inspect(['model' => $wanted]);

            $this->assertSame(0, $code, $wanted);
            $this->assertStringContainsString('posts (key: id)', $out);
            $this->assertStringContainsString('All1\LuModels\Actions\Create', $out);
            $this->assertStringContainsString('DEFAULT', $out);
            $this->assertStringContainsString('secret_token', $out);
            $this->assertStringContainsString('Shown as the headline', $out);
            $this->assertStringContainsString('user()', $out);
            $this->assertStringContainsString('on → lu_models_audit', $out);
        }
    }

    public function test_it_marks_an_app_override(): void
    {
        require_once __DIR__.'/../Fixtures/app-overrides.php';
        Descriptor::forget(Gadget::class);

        [$code, $out] = $this->inspect(['model' => Gadget::class]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('App\LuModels\Actions\CRUD\CreateGadget', $out);
        $this->assertStringContainsString('OVERRIDE', $out);
    }

    public function test_an_unknown_model_fails_and_names_the_known_ones(): void
    {
        [$code, $out] = $this->inspect(['model' => 'Nope']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString("No model matches 'Nope'", $out);
        $this->assertStringContainsString('Post', $out);
    }

    public function test_json_output_is_the_descriptor_and_refresh_clears_the_cache(): void
    {
        Descriptor::for(Post::class);
        $this->assertTrue(Cache::has(Descriptor::cacheKey(Post::class)));

        [$code, $out] = $this->inspect(['model' => 'Post', '--json' => true, '--refresh' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Descriptor cache cleared', $out);
        $decoded = json_decode(substr($out, strpos($out, '{')), true);
        $this->assertSame(Post::class, $decoded['class']);
        $this->assertSame('posts', $decoded['table']);
        $this->assertArrayHasKey('title', $decoded['fields']);
    }
}
