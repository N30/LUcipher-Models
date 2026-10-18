<?php

namespace All1\LuModels\Mcp\Tools;

use All1\LuModels\Mcp\LuModelsServer;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\Exposure;
use All1\LuModels\Support\FieldVisibility;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** One model as the inspector sees it: fields, visibility, rules, relationships and each operation's schema. */
#[IsReadOnly]
#[IsIdempotent]
class DescribeTool extends Tool
{
    protected string $name = 'lu_models_describe';

    protected string $title = 'Describe a model';

    protected string $description = 'A model\'s fields (type, nullable, help, which actions may read and write them), relationships, validation rules and the input schema of each of its tools.';

    public function schema(JsonSchema $schema): array
    {
        $slugs = array_map(fn (string $class) => Descriptor::for($class)->slug, array_keys(Exposure::exposedModels('mcp')));

        return [
            'model' => $schema->string()->enum($slugs)->description('The model, as lu_models_models names it')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $wanted = Str::lower((string) $request->get('model'));
        foreach (array_keys(Exposure::exposedModels('mcp')) as $class) {
            $descriptor = Descriptor::for($class);
            if (in_array($wanted, [Str::lower($descriptor->slug), Str::lower($descriptor->name), Str::lower($class)], true)) {
                return Response::structured($this->describe($descriptor));
            }
        }

        return Response::error('404: No exposed model matches '.json_encode($request->get('model')).'. Call lu_models_models for the list.');
    }

    /** @return array<string, mixed> */
    protected function describe(Descriptor $descriptor): array
    {
        $visibility = FieldVisibility::for(new $descriptor->class);
        $fields = [];
        foreach ($descriptor->fields as $name => $field) {
            $fields[] = [
                'name' => $name,
                'type' => $field['clean_type'],
                'nullable' => $field['nullable'],
                'help' => $field['help'],
                'readable_on' => array_values(array_filter(['index', 'show'], fn ($action) => $visibility->visible($name, $action))),
                'writable_on' => array_values(array_filter(['create', 'edit'], fn ($action) => $visibility->writable($name, $action))),
            ];
        }

        $schemas = [];
        foreach (LuModelsServer::OPERATIONS as $tool => $operation) {
            if ((new $operation)->supports($descriptor, 'mcp')) {
                $instance = new $tool($descriptor);
                $schemas[$instance->name()] = $instance->inputSchema();
            }
        }

        return [
            'model' => $descriptor->slug,
            'name' => $descriptor->name,
            'class' => $descriptor->class,
            'table' => $descriptor->table,
            'key' => $descriptor->key,
            'fields' => $fields,
            'relationships' => array_keys($descriptor->relationships),
            'rules' => $descriptor->rules,
            'policy' => $descriptor->policy,
            'tools' => $schemas,
        ];
    }
}
