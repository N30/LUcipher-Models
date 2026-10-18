<?php

namespace All1\LuModels\Mcp\Tools;

use All1\LuModels\Actions\Update;
use All1\LuModels\Support\Writer;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateTool extends OperationTool
{
    public const VERB = 'update';

    protected function describe(): string
    {
        return 'Update a '.$this->descriptor->headline.' by key; only the fields given change. dry_run checks without writing.';
    }

    public function inputSchema(): array
    {
        return $this->withDryRun($this->withKey((new Update)->schema($this->descriptor)));
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->guarded(fn () => $this->result(
            Writer::update($this->record($request), Arr::except($request->all(), [$this->descriptor->key, 'dry_run']), 'mcp', (bool) $request->get('dry_run', false)),
        ));
    }
}
