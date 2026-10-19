<?php

namespace All1\LuModels\Livewire;

use All1\LuModels\Livewire\Concerns\InteractsWithResource;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\FieldVisibility;
use All1\LuModels\Support\Keys;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** One record on /spa: the same show blade as /web, through the read operation (policy, visibility, events). */
class Show extends Component
{
    use InteractsWithResource;

    #[Locked]
    public mixed $key = null;

    public function mount(string $model, mixed $id): void
    {
        $this->resolveResource($model);
        $this->key = $id;
    }

    /** @return View */
    public function render()
    {
        $record = Keys::find($this->newModel(), $this->key) ?? abort(404, 'Record not found');
        $service = LuModels::fromData('read', $this->service('show'), $record, [], 'spa');
        $model = $service->model;
        $visibility = FieldVisibility::for($model);
        $columns = array_values(array_filter(
            Descriptor::for($model)->legacyColumns(),
            fn (array $column) => $visibility->visible((string) $column['Field'], 'show'),
        ));

        return view('lu_models::livewire.show', [
            'model' => $model,
            'columns' => $columns,
            'db_columns' => $service->db_columns,
            'filters' => [],
            'meta' => $service->controller_data['meta'],
            'route_prefix' => $this->routePrefix,
            'controller_type' => 'spa',
            'action' => 'show',
        ]);
    }
}
