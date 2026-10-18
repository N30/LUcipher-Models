<?php

namespace All1\LuModels\Mcp\Tools;

use All1\LuModels\Actions\Read;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Envelope;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class GetTool extends OperationTool
{
    public const VERB = 'get';

    protected function describe(): string
    {
        return 'Get one '.$this->descriptor->headline.' by key ({data}); include names relationships to load.';
    }

    public function inputSchema(): array
    {
        $schema = (new Read)->schema($this->descriptor);
        $schema['properties']['include'] = ['type' => ['string', 'array'], 'items' => ['type' => 'string', 'enum' => array_keys($this->descriptor->relationships)], 'description' => 'Relationships to include'];

        return $schema;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->guarded(function () use ($request) {
            $record = $this->record($request);
            $service = LuModels::fromData('read', $this->service('show'), $record, array_filter(['include' => $request->get('include')]), 'mcp');

            return Response::structured(Envelope::of($service->model));
        });
    }
}
