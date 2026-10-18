<?php

namespace All1\LuModels\Mcp\Tools;

use All1\LuModels\Mcp\LuModelsServer;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\Exposure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** The models this server exposes, and the tools each one has. */
#[IsReadOnly]
#[IsIdempotent]
class ModelsTool extends Tool
{
    protected string $name = 'lu_models_models';

    protected string $title = 'The exposed models';

    protected string $description = 'The models exposed to agents, with the tools each one offers. Call lu_models_describe for one of them.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $models = [];
        foreach (array_keys(Exposure::exposedModels('mcp')) as $class) {
            $descriptor = Descriptor::for($class);
            $tools = [];
            foreach (LuModelsServer::OPERATIONS as $tool => $operation) {
                if ((new $operation)->supports($descriptor, 'mcp')) {
                    $tools[] = Str::slug($descriptor->slug, '_').'_'.$tool::VERB;
                }
            }
            $models[] = [
                'model' => $descriptor->slug,
                'name' => $descriptor->name,
                'headline' => $descriptor->headline,
                'class' => $descriptor->class,
                'key' => $descriptor->key,
                'tools' => $tools,
            ];
        }

        return Response::structured(['models' => $models, 'count' => count($models)]);
    }
}
