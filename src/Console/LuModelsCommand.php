<?php

namespace All1\LuModels\Console;

use All1\LuModels\Audit\AuditEntry;
use All1\LuModels\Models\LuModel;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\Exposure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The inspector that ships beside the magic: prints what the conventions resolved to, so a typo in
 * an override class name or a missing policy shows up here instead of failing silently at runtime.
 */
class LuModelsCommand extends Command
{
    protected $signature = 'lu:models
        {model? : A model class, its basename or its route slug (e.g. App\\Models\\Post, Post, posts)}
        {--json : Print the descriptor(s) as JSON}
        {--refresh : Drop the cached descriptors first}';

    protected $description = 'Show how lucipher/models resolves each model: transports, policy, operations, events, views, fields.';

    public function handle(): int
    {
        if ($this->option('refresh')) {
            Descriptor::flush();
            $this->components->info('Descriptor cache cleared.');
        }

        $classes = array_keys(LuModel::getModels());

        if ($this->argument('model') !== null) {
            $class = $this->resolve((string) $this->argument('model'), $classes);
            if ($class === null) {
                $this->components->error("No model matches '{$this->argument('model')}'. Known: ".implode(', ', array_map('class_basename', $classes)));

                return self::FAILURE;
            }

            return $this->one(Descriptor::for($class));
        }

        if ($classes === []) {
            $this->components->warn('No models found under '.(config('lu::models.models_path') ?: app_path('Models')).'.');

            return self::SUCCESS;
        }

        $descriptors = array_map(fn ($class) => Descriptor::for($class), $classes);

        if ($this->option('json')) {
            $this->line(json_encode(array_map(fn (Descriptor $d) => $d->toArray(), $descriptors), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info('lucipher/models — '.count($descriptors).' model(s) under '.(config('lu::models.models_path') ?: app_path('Models')));
        $this->table(
            ['Model', 'Table', 'Transports', 'Policy', 'Operations', 'Views'],
            array_map(fn (Descriptor $d) => [
                $d->class,
                $d->table,
                $d->transports === [] ? '— (not exposed)' : implode(', ', $d->transports),
                $this->policyCell($d),
                $this->overridesCell($d),
                $d->overrides['views']['source'] === 'app' ? 'OVERRIDE '.$d->overrides['views']['class'] : 'default',
            ], $descriptors),
        );
        $this->line('  Transports enabled: '.$this->enabledTransports().'. Run `lu:models {model}` for the full resolution table.');

        return self::SUCCESS;
    }

    protected function one(Descriptor $d): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($d->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('<fg=green;options=bold>'.$d->class.'</>', $d->extendsLuModel ? 'extends LuModel' : 'plain Eloquent model');
        $this->components->twoColumnDetail('Table', $d->table.' (key: '.$d->key.')');
        $this->components->twoColumnDetail('Route slug', $d->slug);
        $this->components->twoColumnDetail('Transports', $d->transports === [] ? '<fg=yellow>none — list it in lu::models.models or declare $lu_transports</>' : implode(', ', $d->transports).'  (enabled globally: '.$this->enabledTransports().')');
        $this->components->twoColumnDetail('Policy', $this->policyCell($d));
        $this->components->twoColumnDetail('Rate limits', collect(config('lu::models.rate_limits', []))->map(fn ($v, $k) => $k.'='.($v ?? 'off'))->implode(', '));
        $this->components->twoColumnDetail('Audit', $this->auditCell());
        $this->newLine();

        $this->components->info('Operations and events (DEFAULT = package, OVERRIDE = your app)');
        $this->table(['Rung', 'Resolved to', 'Source'], collect($d->overrides)
            ->except('policy')
            ->map(fn ($o, $rung) => [$rung, $o['class'], $o['source'] === 'app' ? '<fg=cyan>OVERRIDE</>' : 'DEFAULT'])
            ->values()->all());

        $this->components->info('Fields');
        $this->table(
            ['Field', 'Type', 'Input', 'Null', 'Fillable', 'Hidden', 'Visible on', 'Writable on', 'Help'],
            array_map(fn ($f) => [
                $f['name'], $f['type'], $f['input'], $f['nullable'] ? 'yes' : 'no',
                $f['fillable'] ? 'yes' : 'no', $f['hidden'] ? 'yes' : 'no',
                implode(',', $f['visible']) ?: '—', implode(',', $f['writable']) ?: '—',
                Str::limit($f['help'], 40),
            ], $d->fields),
        );

        if ($d->relationships !== []) {
            $this->components->info('Relationships');
            foreach ($d->relationships as $method => $related) {
                $this->components->twoColumnDetail($method.'()', $related ?? '?');
            }
        }

        $this->components->info('Options');
        foreach ($d->options as $key => $value) {
            $this->components->twoColumnDetail($key, is_array($value) ? (implode(', ', $value) ?: '—') : var_export($value, true));
        }
        foreach ($d->rules as $action => $rules) {
            $this->components->twoColumnDetail('rules.'.$action, $rules === [] ? '—' : json_encode($rules));
        }

        return self::SUCCESS;
    }

    /** @param  list<string>  $classes */
    protected function resolve(string $wanted, array $classes): ?string
    {
        $wanted = ltrim($wanted, '\\');
        foreach ($classes as $class) {
            if (strcasecmp($class, $wanted) === 0 || strcasecmp(class_basename($class), $wanted) === 0) {
                return $class;
            }
        }
        foreach ($classes as $class) {
            if (strcasecmp(Descriptor::for($class)->slug, $wanted) === 0) {
                return $class;
            }
        }

        return class_exists($wanted) ? $wanted : null;
    }

    protected function policyCell(Descriptor $d): string
    {
        if ($d->policy !== null) {
            return $d->policy;
        }

        return $d->transports === [] ? '— (none, not exposed)' : '<fg=red>MISSING — exposed without a policy, every signed-in user is denied</>';
    }

    protected function overridesCell(Descriptor $d): string
    {
        $overridden = collect($d->overrides)->except(['views', 'policy'])->filter(fn ($o) => $o['source'] === 'app')->keys();

        return $overridden->isEmpty() ? 'all default' : 'OVERRIDE: '.$overridden->implode(', ');
    }

    protected function enabledTransports(): string
    {
        $on = array_filter(Exposure::TRANSPORTS, fn ($t) => config("lu::models.enable_{$t}_routes") && Route::has("lu::{$t}.resources"));

        return $on === [] ? 'none' : implode(', ', $on);
    }

    protected function auditCell(): string
    {
        if (! config('lu::models.audit.enabled')) {
            return 'off';
        }
        $table = config('lu::models.audit.table', 'lu_models_audit');
        $exists = Schema::connection((new AuditEntry)->getConnectionName())->hasTable($table);

        return $exists ? 'on → '.$table : '<fg=yellow>on, but table '.$table.' is missing (php artisan migrate)</>';
    }
}
