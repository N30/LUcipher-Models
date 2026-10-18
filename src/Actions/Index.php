<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Events\Index as IndexEvent;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Descriptor;

/** List records: the service's index query, then the Index event. */
class Index extends Action
{
    public const VERB = 'Annexation';

    public function name(): string
    {
        return 'index';
    }

    protected function inputSchema(Descriptor $descriptor): array
    {
        $fields = $descriptor->fieldsFor('index');
        $readable = array_keys($fields);
        $relationships = array_keys($descriptor->relationships);
        $filters = [];
        foreach ($fields as $name => $field) {
            $value = Descriptor::jsonSchemaFor($field);
            unset($value['description']);
            $filters[$name] = ['anyOf' => [
                $value,
                [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'eq' => $value, 'ne' => $value, 'lt' => $value, 'lte' => $value, 'gt' => $value, 'gte' => $value,
                        'like' => ['type' => 'string', 'description' => 'Contains'],
                        'in' => ['type' => ['string', 'array'], 'items' => $value, 'description' => 'Comma-separated or an array'],
                        'null' => ['type' => 'boolean'],
                    ],
                ],
            ]];
        }

        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'minimum' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'description' => 'A flat list of at most this many rows instead of a page'],
                'filter' => ['type' => 'object', 'additionalProperties' => false, 'properties' => $filters, 'description' => 'filter[field]=value, or filter[field][op]=value with op one of eq, ne, lt, lte, gt, gte, like, in, null'],
                'sort' => ['type' => ['string', 'array'], 'items' => ['type' => 'string', 'enum' => [...$readable, ...array_map(fn ($f) => '-'.$f, $readable)]], 'description' => 'Field names, comma-separated or an array; a leading - sorts descending'],
                'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'description' => 'Direction of the default sort when sort is not given'],
                'search' => ['type' => 'string'],
                'search_fields' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $readable]],
                'fields' => ['type' => ['string', 'array'], 'items' => ['type' => 'string', 'enum' => $readable], 'description' => 'Sparse fields: only these attributes (the key always)'],
                'include' => ['type' => ['string', 'array'], 'items' => ['type' => 'string', 'enum' => $relationships], 'description' => 'Relationships to include'],
                'with' => ['type' => ['string', 'array'], 'items' => ['type' => 'string', 'enum' => $relationships], 'description' => 'Alias of include'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        $service->controller_index($model, $data);

        $this->dispatchEvent($model, 'Index', IndexEvent::class, $data, $controller_type);

        return $service;
    }
}
