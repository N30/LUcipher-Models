<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Audit\AuditLogger;
use All1\LuModels\Services\LuModels;
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

    /** The state change or query. Returns the service carrying the result. */
    abstract public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels;

    /** Static shim with the signature lorisleiva/laravel-actions gave these classes in 3.0.x. */
    public static function run(...$arguments)
    {
        return app(static::class)->handle(...$arguments);
    }

    /** Authorize, then execute. */
    public function handle(LuModels $service, $model, $data, $controller_type): LuModels
    {
        $service->authorize($this->name(), $model, $data, $controller_type);

        return $this->execute($service, $model, (array) $data, $controller_type);
    }

    /**
     * Execute without authorizing: the app's legacy Verb override when one exists, else perform();
     * then the string event and the audit row for a write. LuModels::fireVerbs() lands here.
     */
    public function execute(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        $before = $model instanceof Model && $model->exists ? $model->getRawOriginal() : [];

        if ($override = $this->legacyVerbOverride($model)) {
            $service = (new $override($service, $model, $data, $controller_type))->handle();
        } else {
            $service = $this->perform($service, $model, $data, $controller_type);
        }

        $event_name = LuModels::stringEventName(static::VERB, $model);
        if ($event_name !== null) {
            $payload_model = $service->model instanceof Model ? $service->model : $model;
            event($event_name, ['model' => $payload_model, 'data' => $data, 'controller_type' => $controller_type]); // <-- THE EVENT YOU WANT TO LISTEN FOR IF USING LARAVEL BUILT-IN EVENTS

            if ($payload_model instanceof Model) {
                AuditLogger::record(Str::afterLast($event_name, '.'), $payload_model, $before, $controller_type);
            }
        }

        return $service;
    }

    /**
     * @deprecated 3.1 The Verb override seam, App\Models\Verbs\{folder}\{Model}{Verb}, still runs when an
     *             app defines it; new code overrides perform() through App\LuModels\Actions instead.
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
     */
    protected function safetyOn($model, string $flag): bool
    {
        return ($model->{$flag} ?? true) !== false;
    }

    /**
     * The app's override for a Laravel Event, e.g. App\Events\CRUD\UserCreated for a User model and
     * the "Created" suffix, or null when the app defines none.
     */
    public static function appEventClass($model, string $suffix): ?string
    {
        $folder = $model->action_folder ?? 'CRUD';
        $class = '\\App\\Events\\'.$folder.'\\'.ucfirst(class_basename($model)).$suffix;

        return class_exists($class) ? $class : null;
    }

    /** Dispatch the app's Event override when it exists, else the package's default Event. */
    protected function dispatchEvent($model, string $suffix, string $default, array $data, ?string $controller_type): void
    {
        $event = static::appEventClass($model, $suffix) ?? $default;
        event(new $event($model, $data, $controller_type));
    }
}
