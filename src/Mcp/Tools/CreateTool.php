<?php

namespace All1\LuModels\Mcp\Tools;

use All1\LuModels\Actions\Create;
use All1\LuModels\Support\Writer;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateTool extends OperationTool
{
    public const VERB = 'create';

    protected function describe(): string
    {
        return 'Create a '.$this->descriptor->headline.'. The schema lists what may be written and what is required; dry_run checks without writing.';
    }

    public function inputSchema(): array
    {
        return $this->withDryRun((new Create)->schema($this->descriptor));
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->guarded(fn () => $this->result(
            Writer::create($this->model(), Arr::except($request->all(), ['dry_run']), 'mcp', (bool) $request->get('dry_run', false)),
        ));
    }
}
