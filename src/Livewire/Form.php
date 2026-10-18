<?php

namespace All1\LuModels\Livewire;

use All1\LuModels\Livewire\Concerns\InteractsWithResource;
use All1\LuModels\Support\Form as FormFields;
use All1\LuModels\Support\Keys;
use All1\LuModels\Support\Writer;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create and edit on /spa: the same form blade as /web, bound to $form. save() goes through the
 * Writer the API uses, so validation, rejected keys and the operations are the same; errors come back
 * by field, success toasts and navigates to the record.
 */
class Form extends Component
{
    use InteractsWithResource;

    #[Locked]
    public mixed $key = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(string $model, mixed $id = null): void
    {
        $this->resolveResource($model);

        $record = $id === null ? null : $this->record($id);
        $this->key = $record?->getKey();

        foreach (FormFields::fields($record ?? $this->newModel(), $this->action()) as $field) {
            $this->form[$field['name']] = $field['value'];
        }
    }

    public function save(): void
    {
        $data = array_map(fn ($value) => $value === '' ? null : $value, $this->form);

        if ($this->key === null) {
            $result = Writer::create($this->newModel(), array_filter($data, fn ($value) => $value !== null), 'spa');
        } else {
            $result = Writer::update($this->record($this->key), $data, 'spa');
        }

        if ($result->failed()) {
            $this->setErrorBag($result->errors);
            $this->toast('warning', 'Oops', $result->message);

            return;
        }

        $this->toast('success', 'Success', $result->message);
        $this->redirectRoute('lu::spa.resources', ['model' => $this->routePrefix, 'action' => 'show', 'id' => $result->model?->getKey()], navigate: true);
    }

    public function render()
    {
        return view('lu_models::livewire.form', [
            'model' => $this->key === null ? $this->newModel() : $this->record($this->key),
            'action' => $this->action(),
            'route_prefix' => $this->routePrefix,
            'controller_type' => 'spa',
            'columns' => [],
            'db_columns' => [],
            'filters' => [],
            'meta' => [],
        ]);
    }

    protected function action(): string
    {
        return $this->key === null ? 'create' : 'edit';
    }

    protected function record(mixed $id): Model
    {
        return Keys::find($this->newModel(), $id) ?? abort(404, 'Record not found');
    }
}
