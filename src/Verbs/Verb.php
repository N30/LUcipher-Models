<?php

namespace All1\LuModels\Verbs;

use Thunk\Verbs\Event;

/**
 * The shared shape of the five CRUD+I Verbs (Creation, Revelation, Updation, Deletion, Annexation).
 *
 * v3.0.1: one constructor for all five, so $service is always assigned (Deletion and Updation
 * never set it in 3.0.0, which made destroy fatal), the properties are declared (no PHP 8.2
 * dynamic-property deprecations), and the app-level Laravel Event override is looked up by the
 * MODEL's name (App\Events\CRUD\UserCreated), as documented, rather than the Verb's own name
 * (App\Events\CRUD\CreationCreated). Every handle() returns the service.
 */
abstract class Verb extends Event
{
    /** @var \All1\LuModels\Services\LuModels */
    public $service;

    /** @var \Illuminate\Database\Eloquent\Model */
    public $model;

    public $data;

    public $controller_type;

    public function __construct($service, $model, $data, $controller_type)
    {
        $this->service = $service;
        $this->model = $model;
        $this->data = $data;
        $this->controller_type = $controller_type;
    }

    /**
     * Whether a safety flag is on for this model. Fails closed: a model that does not declare the
     * flag (any plain Eloquent model, or a LuModel that leaves the default) is treated as safe,
     * i.e. writes go through create()/update() and respect $fillable. Only an explicit `false`
     * opts a model out.
     */
    protected function safetyOn($model, string $flag): bool
    {
        return ($model->{$flag} ?? true) !== false;
    }

    /**
     * The app's override for a Laravel Event, e.g. App\Events\CRUD\UserCreated for a User model
     * and the "Created" suffix, or null when the app defines none.
     */
    public static function appEventClass($model, string $suffix): ?string
    {
        $folder = $model->action_folder ?? 'CRUD';
        $class = '\\App\\Events\\'.$folder.'\\'.ucfirst(class_basename($model)).$suffix;

        return class_exists($class) ? $class : null;
    }

    /** Dispatch the app's Event override when it exists, else the package's default Event. */
    protected function dispatchEvent($model, string $suffix, string $default): void
    {
        $event = static::appEventClass($model, $suffix) ?? $default;
        event(new $event($model, $this->data, $this->controller_type));
    }
}
