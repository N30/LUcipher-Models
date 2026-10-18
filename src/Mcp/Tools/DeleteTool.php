<?php

namespace All1\LuModels\Mcp\Tools;

use All1\LuModels\Actions\Delete;
use All1\LuModels\Support\Writer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteTool extends OperationTool
{
    public const VERB = 'delete';

    protected function describe(): string
    {
        return 'Delete a '.$this->descriptor->headline.' by key. dry_run checks without deleting.';
    }

    public function inputSchema(): array
    {
        return $this->withDryRun((new Delete)->schema($this->descriptor));
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->guarded(fn () => $this->result(
            Writer::delete($this->record($request), 'mcp', (bool) $request->get('dry_run', false)),
        ));
    }
}
