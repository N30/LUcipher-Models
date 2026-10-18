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
        $readable = array_keys($descriptor->fieldsFor('index'));

        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'minimum' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'description' => 'A flat list of at most this many rows instead of a page'],
                'order' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                'search' => ['type' => 'string'],
                'search_fields' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $readable]],
                'with' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys($descriptor->relationships)]],
            ],
            'additionalProperties' => false,
        ];
    }

    public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        $service->controller_index($model);

        $this->dispatchEvent($model, 'Index', IndexEvent::class, $data, $controller_type);

        return $service;
    }
}
