<?php

namespace All1\LuModels\Console;

use All1\LuModels\Support\Exposure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The first command an app runs: publish the config and the audit migration (and the views on
 * request), flip the transport switches it is asked to, and say the three things that remain in the
 * reader's own words. Idempotent: a second run changes nothing and says so.
 */
class InstallCommand extends Command
{
    protected $signature = 'lu:install
        {--transports= : Transports to turn on in the published config, e.g. web,api}
        {--views : Publish the blades to resources/views/vendor/lu_models}
        {--mcp : Also explain the MCP transport}
        {--force : Overwrite an already published config}';

    protected $description = 'Publish lucipher/models (config, audit migration, optionally views) and print what to do next.';

    public function handle(): int
    {
        $transports = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('transports')))));
        $unknown = array_diff($transports, Exposure::TRANSPORTS);
        if ($unknown !== []) {
            $this->components->error('Unknown transport(s): '.implode(', ', $unknown).'. Known: '.implode(', ', Exposure::TRANSPORTS).'.');

            return self::FAILURE;
        }

        $config = config_path('lu/models.php');
        if (File::exists($config) && ! $this->option('force')) {
            $this->components->twoColumnDetail('config/lu/models.php', '<fg=yellow>already published, left as is</>');
        } else {
            $this->callSilently('vendor:publish', ['--tag' => 'lu-models-config', '--force' => (bool) $this->option('force')]);
            $this->components->twoColumnDetail('config/lu/models.php', '<info>published</>');
        }

        $before = count(File::glob(database_path('migrations/*_create_lu_models_audit_table.php')));
        $this->callSilently('vendor:publish', ['--tag' => 'lu-models-migrations']);
        $after = count(File::glob(database_path('migrations/*_create_lu_models_audit_table.php')));
        $this->components->twoColumnDetail('database/migrations/…_create_lu_models_audit_table.php', $after > $before ? '<info>published</>' : '<fg=yellow>already present</>');

        if ($this->option('views')) {
            $this->callSilently('vendor:publish', ['--tag' => 'lu-models-views', '--force' => (bool) $this->option('force')]);
            $this->components->twoColumnDetail('resources/views/vendor/lu_models', '<info>published</>');
        }

        if ($transports !== []) {
            $this->enable($config, $transports);
        }

        $this->newLine();
        $this->components->info('Next');
        $this->line('  1. Expose a model: in config/lu/models.php, <comment>\'models\' => [App\\Models\\Post::class => [\'web\', \'api\']]</comment>,');
        $this->line('     or on the model itself, <comment>public $lu_transports = [\'web\', \'api\'];</comment>. Nothing is exposed until you do.');
        $this->line('  2. Give it a policy: <comment>php artisan make:policy PostPolicy --model=Post</comment>. A signed-in user is denied without one.');
        $this->line('  3. Turn a transport on: <comment>\'enable_web_routes\' => true</comment> (or run this command with <comment>--transports=web,api</comment>),');
        $this->line('     then <comment>php artisan migrate</comment> for the audit table and <comment>php artisan lu:models</comment> to see what resolved.');
        if ($this->option('mcp')) {
            $this->line('  4. For agents: <comment>composer require laravel/mcp</comment>, <comment>\'enable_mcp_routes\' => true</comment>, expose the model on <comment>mcp</comment>,');
            $this->line('     then <comment>php artisan mcp:start lu-models</comment> locally or point a client at <comment>/mcp/lu-models</comment> with a Sanctum token.');
        }

        return self::SUCCESS;
    }

    /**
     * Flip the enable_{transport}_routes switches in the published config and nothing else.
     *
     * @param  list<string>  $transports
     */
    protected function enable(string $config, array $transports): void
    {
        $contents = (string) File::get($config);
        $flipped = [];
        $missing = [];
        foreach ($transports as $transport) {
            $key = "'enable_{$transport}_routes'";
            $pattern = '/'.preg_quote($key, '/').'\s*=>\s*(true|false)/';
            if (! preg_match($pattern, $contents)) {
                $missing[] = $transport;

                continue;
            }
            $contents = (string) preg_replace($pattern, $key.' => true', $contents, 1);
            $flipped[] = $transport;
        }
        File::put($config, $contents);

        if ($flipped !== []) {
            $this->components->twoColumnDetail('transports enabled', '<info>'.implode(', ', $flipped).'</info>');
        }
        if ($missing !== []) {
            $this->components->warn('config/lu/models.php has no enable_*_routes line for: '.implode(', ', $missing).'. Add it by hand.');
        }
    }
}
