<?php

namespace All1\LuModels\View\Components;

use All1\LuModels\Support\Ui;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

/**
 * The listing table. Its columns are the model's `index_columns` when it names them (3.0.x declared
 * the property and never applied it), else every column the index action may read; dotted columns
 * show a relationship's attribute. Sorting links toggle the `sort` parameter.
 */
class IndexTable extends Component
{
    public $data;

    public $type;

    public $dbColumns;

    public $route_prefix;

    public ?Model $model;

    public function __construct($data, $dbColumns, $routePrefix, $type, $model = null)
    {
        $this->data = $data;
        $this->type = $type;
        $this->dbColumns = $dbColumns;
        $this->route_prefix = $routePrefix;
        $this->model = $model instanceof Model ? $model : null;
    }

    /** @return list<string> */
    protected function columns(): array
    {
        $readable = array_values(array_map('strval', (array) ($this->dbColumns ?? [])));
        $preferred = array_values(array_map('strval', (array) ($this->model->index_columns ?? [])));

        return $preferred === [] ? $readable : array_values(array_intersect($preferred, $readable));
    }

    /** @return list<array{label: string, href: string|null, direction: string|null}> */
    protected function headers(): array
    {
        $current = (string) request()->input('sort', '');
        $headers = [];
        foreach ($this->columns() as $column) {
            $sortable = ! str_contains($column, '.');
            $direction = $current === $column ? 'asc' : ($current === '-'.$column ? 'desc' : null);
            $headers[] = [
                'label' => Ui::label($column),
                'href' => $sortable ? request()->fullUrlWithQuery(['sort' => $direction === 'asc' ? '-'.$column : $column, 'page' => null]) : null,
                'direction' => $direction,
            ];
        }
        $headers[] = ['label' => '', 'href' => null, 'direction' => null];

        return $headers;
    }

    /**
     * The values of a dotted column (`user.name`) on a row: one per related record.
     *
     * @return list<string>
     */
    public function relatedValues(Model $row, string $column): array
    {
        $segments = explode('.', $column);
        $attribute = array_pop($segments);
        $related = $row;
        foreach ($segments as $segment) {
            $related = $related instanceof Model ? $related->getRelation($segment) : null;
            if ($related === null) {
                return [];
            }
        }

        $items = is_iterable($related) ? $related : [$related];
        $values = [];
        foreach ($items as $item) {
            if ($item instanceof Model) {
                $values[] = method_exists($item, 'displayText') ? (string) $item->displayText($attribute) : $this->cellText($item->getAttribute($attribute));
            }
        }

        return $values;
    }

    public function cellText(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i'),
            is_bool($value) => $value ? '✓' : '—',
            is_array($value) => (string) json_encode($value),
            default => (string) $value,
        };
    }

    public function render(): View|Closure|string
    {
        return view('lu_models::index-table')
            ->with('data', $this->data)
            ->with('columns', $this->columns())
            ->with('headers', $this->headers())
            ->with('route_prefix', $this->route_prefix)
            ->with('type', $this->type); // relatedValues() and cellText() reach the view as callables, as public methods do
    }
}
