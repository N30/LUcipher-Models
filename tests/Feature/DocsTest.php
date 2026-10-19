<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/** Phase 6 §5: README.md and UPGRADE.md are read against the code. */
class DocsTest extends TestCase
{
    /** @return list<string> */
    private function backticked(string $file, string $pattern): array
    {
        preg_match_all('/`([^`]+)`/', (string) file_get_contents(dirname(__DIR__, 2).'/'.$file), $m);
        $hits = [];
        foreach ($m[1] as $code) {
            if (preg_match_all($pattern, $code, $found)) {
                array_push($hits, ...$found[1]);
            }
        }

        return array_values(array_unique($hits));
    }

    public function test_every_config_key_the_docs_name_exists(): void
    {
        $config = config('lu::models');
        foreach (['README.md', 'UPGRADE.md', 'CHANGELOG.md'] as $file) {
            foreach ($this->backticked($file, '/lu::models\.([a-z_\.]+[a-z])/') as $key) {
                $this->assertTrue(Arr::has($config, $key), "$file names lu::models.$key, which the config does not have");
            }
        }
    }

    public function test_every_artisan_command_the_docs_name_exists(): void
    {
        $commands = array_keys(Artisan::all());
        foreach (['README.md', 'UPGRADE.md'] as $file) {
            foreach ($this->backticked($file, '/\b(lu:[a-z\-]+)/') as $command) {
                $this->assertContains($command, $commands, "$file names $command");
            }
        }
    }

    public function test_every_package_class_the_docs_name_exists(): void
    {
        foreach (['README.md', 'UPGRADE.md'] as $file) {
            foreach ($this->backticked($file, '/(All1\\\\LuModels\\\\[A-Za-z\\\\]+)/') as $class) {
                $this->assertTrue(class_exists($class) || trait_exists($class), "$file names $class");
            }
            foreach ($this->backticked($file, '/(lu::(?:web|rest|api|spa)\.[a-z\.]+[a-z])/') as $route) {
                $this->assertTrue(Route::has($route), "$file names route $route");
            }
        }
    }
}
