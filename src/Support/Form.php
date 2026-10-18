<?php

namespace All1\LuModels\Support;

use All1\LuModels\Actions\Create;
use All1\LuModels\Actions\Update;
use All1\LuModels\Services\LuModels;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The fields a create or edit form shows, from the same place the API's contract comes from: the
 * operation's schema() (which fields, which are required, how long) and the descriptor (input kind,
 * help text, default). The form and the API cannot disagree about what a write accepts.
 */
final class Form
{
    /**
     * @param  array<string, mixed>  $old  the previous input to prefer (old())
     * @return list<array{name: string, label: string, input: string, type: string, required: bool, maxlength: int|null, help: string, value: mixed}>
     */
    public static function fields(Model $model, string $action, array $old = []): array
    {
        $action = FieldVisibility::normalizeAction($action);
        $descriptor = Descriptor::for($model);
        $schema = (new ($action === 'create' ? Create::class : Update::class))->schema($descriptor);
        $names = array_keys($schema['properties']);

        // 3.0.x let a super admin reassign the owner on edit; the package injects user_id otherwise
        if ($action === 'edit' && $descriptor->field('user_id') !== null && LuModels::isSuperAdmin() && FieldVisibility::for($model)->writable('user_id', 'edit')) {
            array_unshift($names, 'user_id');
        }

        $fields = [];
        foreach ($names as $name) {
            $field = $descriptor->field($name) ?? [];
            $property = $schema['properties'][$name] ?? [];
            $value = array_key_exists($name, $old) ? $old[$name] : ($model->exists ? $model->getAttribute($name) : ($field['default'] ?? null));

            $fields[] = [
                'name' => $name,
                'label' => Ui::label($name),
                'input' => (string) ($field['input'] ?? 'text'),
                'type' => self::htmlType($field),
                'required' => in_array($name, $schema['required'] ?? [], true),
                'maxlength' => isset($property['maxLength']) ? (int) $property['maxLength'] : null,
                'help' => (string) ($field['help'] ?? ''),
                'value' => self::displayValue($value, $field),
            ];
        }

        return $fields;
    }

    /** @param  array<string, mixed>  $field */
    private static function htmlType(array $field): string
    {
        return match (strtolower((string) ($field['clean_type'] ?? ''))) {
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime-local',
            'time' => 'time',
            'int', 'integer', 'bigint', 'smallint', 'mediumint', 'float', 'double', 'decimal', 'numeric', 'real' => 'number',
            'tinyint', 'boolean', 'bool' => 'checkbox',
            default => Str::contains((string) ($field['name'] ?? ''), 'email') ? 'email' : 'text',
        };
    }

    /** @param  array<string, mixed>  $field */
    private static function displayValue(mixed $value, array $field): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return match (self::htmlType($field)) {
                'date' => $value->format('Y-m-d'),
                'datetime-local' => $value->format('Y-m-d\TH:i'),
                'time' => $value->format('H:i'),
                default => $value->format(DateTimeInterface::ATOM),
            };
        }

        return $value;
    }
}
