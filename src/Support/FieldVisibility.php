<?php

namespace All1\LuModels\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Which attributes of a model each action may read or write.
 *
 * Reads never include Eloquent's $hidden or LuModel's $hidden_columns. A model may narrow further
 * with `public $lu_fields = ['body' => ['show', 'create', 'edit'], 'secret_token' => []]`: a field
 * named there is visible and writable only for the actions listed; unnamed fields keep the default.
 * Writes also respect $fillable / $guarded, and a client key that may not be written is rejected
 * (422) rather than dropped, so the caller learns the contract.
 */
final class FieldVisibility
{
    /** Request actions, under the names a model uses in $lu_fields. */
    private const ACTION_ALIASES = ['store' => 'create', 'update' => 'edit', 'read' => 'show'];

    private const READ_ACTIONS = ['index', 'show'];

    private function __construct(private Model $model) {}

    public static function for(Model $model): self
    {
        return new self($model);
    }

    public static function normalizeAction(string $action): string
    {
        return self::ACTION_ALIASES[$action] ?? $action;
    }

    public function visible(string $field, string $action): bool
    {
        $action = self::normalizeAction($action);

        if (in_array($action, self::READ_ACTIONS, true) && $this->neverRead($field)) {
            return false;
        }

        $map = $this->model->lu_fields ?? null;
        if (is_array($map) && array_key_exists($field, $map)) {
            return in_array($action, array_map([self::class, 'normalizeAction'], (array) $map[$field]), true);
        }

        return true;
    }

    public function writable(string $field, string $action): bool
    {
        return $this->visible($field, $action) && $this->model->isFillable($field);
    }

    /**
     * @param  list<string>  $keys  the keys a client sent
     * @return list<string> those $action may not write
     */
    public function rejectedWrites(array $keys, string $action): array
    {
        return array_values(array_filter($keys, fn ($key) => ! $this->writable((string) $key, $action)));
    }

    /**
     * @param  list<string>  $fields
     * @return list<string> the fields $action may read; dotted relation columns pass through
     */
    public function readable(array $fields, string $action): array
    {
        return array_values(array_filter($fields, fn ($field) => str_contains((string) $field, '.') || $this->visible((string) $field, $action)));
    }

    /** @return list<string> the attributes of $row to hide for $action, beyond Eloquent's own $hidden */
    public function hiddenFor(Model $row, string $action): array
    {
        return array_values(array_filter(array_keys($row->getAttributes()), fn ($field) => ! $this->visible($field, $action)));
    }

    /**
     * @param  array<int, array<string, mixed>|object>  $columns  schema descriptions with a Field key
     * @return array<int, array<string, mixed>|object> those the form for $action may write
     */
    public function filterColumns(array $columns, string $action): array
    {
        return array_values(array_filter($columns, fn ($column) => $this->writable((string) (is_array($column) ? ($column['Field'] ?? '') : ($column->Field ?? '')), $action)));
    }

    private function neverRead(string $field): bool
    {
        return in_array($field, $this->model->getHidden(), true)
            || in_array($field, (array) ($this->model->hidden_columns ?? []), true);
    }
}
