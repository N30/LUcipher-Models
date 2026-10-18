<?php

namespace All1\LuModels\Mcp;

use All1\LuModels\Actions\Create;
use All1\LuModels\Actions\Delete;
use All1\LuModels\Actions\Index;
use All1\LuModels\Actions\Read;
use All1\LuModels\Actions\Update;
use All1\LuModels\Mcp\Tools\CreateTool;
use All1\LuModels\Mcp\Tools\DeleteTool;
use All1\LuModels\Mcp\Tools\DescribeTool;
use All1\LuModels\Mcp\Tools\GetTool;
use All1\LuModels\Mcp\Tools\ListTool;
use All1\LuModels\Mcp\Tools\ModelsTool;
use All1\LuModels\Mcp\Tools\UpdateTool;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\Exposure;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Transport\StdioTransport;

/**
 * The fifth mouth. One tool per model exposed on the mcp transport and per operation it supports, whose
 * input schema is the operation's own schema(), plus two to find one's way: lu_models_models and
 * lu_models_describe. Every call goes through the same operations, policies, audit and rate limits as
 * the other transports; a write with dry_run answers in the conditional and changes nothing.
 */
class LuModelsServer extends Server
{
    /** tool class => the operation whose supports() and schema() it mirrors */
    public const OPERATIONS = [
        ListTool::class => Index::class,
        GetTool::class => Read::class,
        CreateTool::class => Create::class,
        UpdateTool::class => Update::class,
        DeleteTool::class => Delete::class,
    ];

    protected string $name = 'lucipher/models';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        The models this application exposes to agents. Call lu_models_models to see them and
        lu_models_describe for one model's fields, rules and the schema of each operation. Every call is
        authorized by the application's policies as the connected user. A write (create, update, delete)
        accepts dry_run: true to be checked, authorized and validated, without being made; the answer
        then speaks in the conditional ("Would create Post") and nothing is written.
        MARKDOWN;

    protected function boot(): void
    {
        $this->name = (string) (config('lu::models.mcp.name') ?: config('app.name', 'Laravel').' models');
        $this->version = (string) config('lu::models.openapi.version', '1.0.0');

        $localUser = config('lu::models.mcp.local_user');
        if ($this->transport instanceof StdioTransport && $localUser !== null && ! Auth::check()) {
            Auth::onceUsingId($localUser);
        }

        $this->tools = [new ModelsTool, new DescribeTool];
        foreach (array_keys(Exposure::exposedModels('mcp')) as $class) {
            $descriptor = Descriptor::for($class);
            foreach (self::OPERATIONS as $tool => $operation) {
                if ((new $operation)->supports($descriptor, 'mcp')) {
                    $this->tools[] = new $tool($descriptor);
                }
            }
        }
    }
}
