<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Audit\AuditLogger;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\Result;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One class per operation: Index, Read, Create, Update, Delete.
 *
 * handle() is authorize-then-perform. perform() owns the state change (or the query) and is the
 * method an app subclass overrides; the app's class at App\LuModels\Actions\{folder}\{Action}{Model}
 * replaces the whole operation (see LuModels::actionClass()). After a write the service fires the
 * {model}.{created|updated|deleted} string event and records the audit row.
 *
 * 3.0.x split each operation across an Action (authorize) and a Verb (write) and reached one save()
 * through six frames; the Verbs survive as deprecated adapters (see Verbs\Verb).
 */
abstract class Action
{
    /** The legacy Verb name this operation replaces (Creation, Updation, …), for the string event map. */
    public const VERB = '';

    /** The request-facing name policies and public_* config use: index|create|show|update|delete. */
    abstract public function name(): string;

    /**
     * The state change or query. Returns the service carrying the result.
     *
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     */
    abstract public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels;

    /**
     * Whether this operation applies to $model: the model is exposed, on $transport when one is given.
     * Phase 5's OpenAPI document and MCP tools ask this before listing an operation.
     */
    public function supports(Descriptor|Model|string $model, ?string $transport = null): bool
    {
        $descriptor = $model instanceof Descriptor ? $model : Descriptor::for($model);

        if ($descriptor->transports === []) {
            return false;
        }

        return $transport === null || in_array($transport, $descriptor->transports, true);
    }

    /**
     * The input this operation accepts for $model, as a JSON Schema (2020-12 subset) object: the
     * writable fields for a write, the query parameters for a listing, the key for a read or a delete.
     * Derived from the descriptor, so a column comment becomes a property description and a max:N
     * rule a maxLength.
     *
     * @return array<string, mixed>
     */
    public function schema(Descriptor|Model|string $model): array
    {
        return $this->inputSchema($model instanceof Descriptor ? $model : Descriptor::for($model));
    }

    /** @return array<string, mixed> */
    protected function inputSchema(Descriptor $descriptor): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    /**
     * The schema of a write: the fields the action may write, minus what the package injects.
     *
     * @return array<string, mixed>
     */
    protected function writeSchema(Descriptor $descriptor, string $formAction, bool $requireNonNullable): array
    {
        $properties = [];
        $required = [];
        $rules = $descriptor->rules[$formAction === 'create' ? 'create' : 'update'] ?? [];

        foreach ($descriptor->fieldsFor($formAction) as $name => $field) {
            if (in_array($name, Descriptor::INJECTED, true)) {
                continue;
            }
            $ruleList = Descriptor::ruleList($rules[$name] ?? []);
            $properties[$name] = Descriptor::jsonSchemaFor($field, $ruleList);

            if (in_array('required', $ruleList, true) || ($requireNonNullable && ! $field['nullable'] && $field['default'] === null && ! $field['auto_increment'])) {
                $required[] = $name;
            }
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    /**
     * The schema of an operation that names one record.
     *
     * @return array<string, mixed>
     */
    protected function keySchema(Descriptor $descriptor): array
    {
        return [
            'type' => 'object',
            'properties' => [$descriptor->key => ['type' => ['integer', 'string'], 'description' => 'The record key, plain or encrypted']],
            'required' => [$descriptor->key],
            'additionalProperties' => false,
        ];
    }

    /**
     * Static shim with the signature lorisleiva/laravel-actions gave these classes in 3.0.x.
     *
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function run(...$arguments)
    {
        return app(static::class)->handle(...$arguments);
    }

    /**
     * Authorize, then execute.
     *
     * @param  Model  $model
     * @param  mixed  $data
     * @param  string|null  $controller_type
     */
    public function handle(LuModels $service, $model, $data, $controller_type): LuModels
    {
        $service->authorize($this->name(), $model, $data, $controller_type);

        return $this->execute($service, $model, (array) $data, $controller_type);
    }

    /**
     * Authorize, then stop: what this operation would do with $data, in the conditional, so no
     * transport can mistake it for work done (study §2.5). Nothing is written, no event fires, no
     * audit row is recorded. The transport validates before calling this, as it does before handle().
     *
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     */
    public function dryRun(LuModels $service, $model, array $data, ?string $controller_type): Result
    {
        $service->authorize($this->name(), $model, $data, $controller_type);

        return Result::would($this->would($model, $data), $data);
    }

    /**
     * The conditional sentence of a dry run: "Would create Post", "Would update Post #5".
     *
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     */
    public function would($model, array $data): string
    {
        $key = $model->exists ? ' #'.$model->getKey() : '';

        return 'Would '.$this->name().' '.class_basename($model).$key;
    }

    /**
     * Execute without authorizing: the app's legacy Verb override when one exists, else perform();
     * then the string event and the audit row for a write. LuModels::fireVerbs() lands here.
     *
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     */
    public function execute(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        $before = $model->exists ? $model->getRawOriginal() : [];

        if ($override = $this->legacyVerbOverride($model)) {
            $service = (new $override($service, $model, $data, $controller_type))->handle();
        } else {
            $service = $this->perform($service, $model, $data, $controller_type);
        }

        $event_name = LuModels::stringEventName(static::VERB, $model);
        if ($event_name !== null) {
            $payload_model = $service->model ?? $model;
            event($event_name, ['model' => $payload_model, 'data' => $data, 'controller_type' => $controller_type]); // <-- THE EVENT YOU WANT TO LISTEN FOR IF USING LARAVEL BUILT-IN EVENTS

            AuditLogger::record(Str::afterLast($event_name, '.'), $payload_model, $before, $controller_type);
        }

        return $service;
    }

    /**
     * @deprecated 3.1 The Verb override seam, App\Models\Verbs\{folder}\{Model}{Verb}, still runs when an
     *             app defines it; new code overrides perform() through App\LuModels\Actions instead.
     *
     * @param  mixed  $model
     */
    protected function legacyVerbOverride($model): ?string
    {
        if (static::VERB === '' || ! is_object($model)) {
            return null;
        }
        $class = '\App\Models\Verbs\\'.($model->action_folder ?? 'CRUD').'\\'.ucfirst(class_basename($model)).Str::studly(static::VERB);

        return class_exists($class) ? $class : null;
    }

    /**
     * Whether a write-safety flag is on for this model. Fails closed: a model that does not declare the
     * flag is treated as safe, i.e. writes go through create()/update() and respect $fillable. Only an
     * explicit `false` opts a model out.
     *
     * @param  Model  $model
     */
    protected function safetyOn($model, string $flag): bool
    {
        return ($model->{$flag} ?? true) !== false;
    }

    /**
     * The app's override for a Laravel Event, e.g. App\Events\CRUD\UserCreated for a User model and
     * the "Created" suffix, or null when the app defines none.
     *
     * @param  Model  $model
     */
    public static function appEventClass($model, string $suffix): ?string
    {
        $folder = $model->action_folder ?? 'CRUD';
        $class = '\\App\\Events\\'.$folder.'\\'.ucfirst(class_basename($model)).$suffix;

        return class_exists($class) ? $class : null;
    }

    /**
     * Dispatch the app's Event override when it exists, else the package's default Event.
     *
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     */
    protected function dispatchEvent($model, string $suffix, string $default, array $data, ?string $controller_type): void
    {
        $event = static::appEventClass($model, $suffix) ?? $default;
        event(new $event($model, $data, $controller_type));
    }
}
