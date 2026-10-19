<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/** Phase 6 §1: one command publishes what the app needs and says what remains, and never does it twice. */
class InstallCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanPublished();
    }

    protected function tearDown(): void
    {
        $this->cleanPublished(); // the published config would otherwise shadow every later test's config

        parent::tearDown();
    }

    private function cleanPublished(): void
    {
        File::delete(config_path('lu/models.php'));
        File::deleteDirectory(resource_path('views/vendor/lu_models'));
        foreach (File::glob(database_path('migrations/*_create_lu_models_audit_table.php')) as $file) {
            File::delete($file);
        }
    }

    public function test_it_publishes_the_config_and_the_migration_and_says_what_to_do_next(): void
    {
        $this->assertSame(0, Artisan::call('lu:install'));
        $output = Artisan::output();

        $this->assertFileExists(config_path('lu/models.php'));
        $this->assertCount(1, File::glob(database_path('migrations/*_create_lu_models_audit_table.php')));
        $this->assertStringContainsString('published', $output);
        $this->assertStringContainsString('Expose a model', $output);
        $this->assertStringContainsString('make:policy', $output);
        $this->assertStringContainsString('php artisan migrate', $output);
        $this->assertStringNotContainsString('mcp:start', $output);
        $this->assertDirectoryDoesNotExist(resource_path('views/vendor/lu_models'));

        $this->assertSame(0, Artisan::call('lu:install', ['--mcp' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('already published', $output);
        $this->assertStringContainsString('already present', $output);
        $this->assertStringContainsString('mcp:start lu-models', $output);
        $this->assertCount(1, File::glob(database_path('migrations/*_create_lu_models_audit_table.php')), 'the migration is published once');
    }

    public function test_transports_flip_only_their_switches(): void
    {
        $this->assertSame(0, Artisan::call('lu:install', ['--transports' => 'web, api']));
        $published = File::get(config_path('lu/models.php'));

        $this->assertStringContainsString("'enable_web_routes' => true", $published);
        $this->assertStringContainsString("'enable_api_routes' => true", $published);
        $this->assertStringContainsString("'enable_spa_routes' => false", $published);
        $this->assertStringContainsString("'enable_mcp_routes' => false", $published);
        $this->assertStringContainsString("'models' => [", $published, 'the rest of the file is untouched');
        $this->assertStringContainsString('transports enabled', Artisan::output());

        $this->assertSame(1, Artisan::call('lu:install', ['--transports' => 'ftp']));
        $this->assertStringContainsString('Unknown transport(s): ftp', Artisan::output());
    }

    public function test_views_are_published_on_request(): void
    {
        $this->assertSame(0, Artisan::call('lu:install', ['--views' => true]));

        $this->assertFileExists(resource_path('views/vendor/lu_models/web/index.blade.php'));
        $this->assertFileExists(resource_path('views/vendor/lu_models/components/ui/button.blade.php'));
    }
}
