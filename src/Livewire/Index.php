<?php

namespace All1\LuModels\Livewire;

use All1\LuModels\Livewire\Concerns\InteractsWithResource;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Keys;
use All1\LuModels\Support\Writer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The listing on /spa: the same index blade as /web, live. Search, sort, include and page size are the
 * component's state (and the URL's); the query is the one the API runs, fed that state as data.
 */
class Index extends Component
{
    use InteractsWithResource;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $sort = null;

    /** @var list<string> */
    #[Url]
    public array $include = [];

    #[Url]
    public int $perPage = 15;

    public function mount(string $model): void
    {
        $this->resolveResource($model);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedInclude(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /** Toggle: a column, then its descending form. */
    public function sortBy(string $column): void
    {
        $this->sort = $this->sort === $column ? '-'.$column : $column;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'sort', 'include');
        $this->resetPage();
    }

    public function delete(mixed $key): void
    {
        $record = Keys::find($this->newModel(), $key);
        if ($record === null) {
            $this->toast('warning', 'Oops', 'Record not found');

            return;
        }

        $result = Writer::delete($record, 'spa');
        $this->toast('success', 'Deleted', $result->message);
        $this->resetPage();
    }

    /** @return View */
    public function render()
    {
        $params = array_filter(
            ['search' => $this->search, 'sort' => $this->sort, 'include' => $this->include, 'per_page' => $this->perPage],
            fn ($value) => $value !== '' && $value !== null && $value !== [],
        );
        $service = LuModels::fromData('index', $this->service('index'), $this->newModel(), $params, 'spa');

        return view('lu_models::livewire.index', [
            'data' => $service->data,
            'model' => $service->model,
            'db_columns' => $service->db_columns,
            'filters' => $service->filters,
            'columns' => $service->controller_data['columns'],
            'meta' => $service->controller_data['meta'],
            'route_prefix' => $this->routePrefix,
            'controller_type' => 'spa',
            'action' => 'index',
        ]);
    }
}
