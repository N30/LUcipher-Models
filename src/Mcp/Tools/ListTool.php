<?php

namespace All1\LuModels\Mcp\Tools;

use All1\LuModels\Actions\Index;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Envelope;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class ListTool extends OperationTool
{
    public const VERB = 'list';

    protected function describe(): string
    {
        return 'List '.Str::plural($this->descriptor->headline).': a page ({data, meta, links}) with the JSON API\'s grammar (filter, sort, search, fields, include, page, per_page). Unknown or unreadable names are refused by name.';
    }

    public function inputSchema(): array
    {
        return (new Index)->schema($this->descriptor);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->guarded(function () use ($request) {
            $service = LuModels::fromData('index', $this->service('index'), $this->model(), $request->all(), 'mcp');

            return Response::structured(Envelope::of($service->data));
        });
    }
}
