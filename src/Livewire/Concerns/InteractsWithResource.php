<?php

namespace All1\LuModels\Livewire\Concerns;

use All1\LuModels\Services\LuModels;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * What the /spa components share: the model resolved once from the route slug (and 404 when it is
 * unknown or not exposed on spa), a service shaped like the controllers', the toast and the URLs.
 * The surface is derived once at the entry point and threaded down as data (study §6.5).
 */
trait InteractsWithResource
{
    #[Locked]
    public string $modelClass = '';

    #[Locked]
    public string $routePrefix = '';

    protected function resolveResource(string $model): void
    {
        $service = new LuModels;
        $service->controller_initialize('spa', $model);

        $this->modelClass = get_class($service->model);
        $this->routePrefix = $model;
    }

    protected function newModel(): Model
    {
        return new $this->modelClass;
    }

    /** A service initialized the way the controllers initialize theirs, for one action, without a route. */
    protected function service(string $action): LuModels
    {
        return LuModels::forTransport('spa', $this->modelClass, $action);
    }

    /** Onto the user's stream, not into the component's own view (study §2.7): the page's toasts component picks it up. */
    protected function toast(string $type, string $title, string $message): void
    {
        $this->dispatch('lu-models:toast', type: $type, title: $title, message: $message);
    }
}
