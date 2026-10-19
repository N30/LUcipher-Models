<?php

namespace All1\LuModels\Console;

use All1\LuModels\Support\OpenApi;
use Illuminate\Console\Command;

/** Write the OpenAPI 3.1 document the API serves at /api/openapi.json, for tooling and for review. */
class OpenApiCommand extends Command
{
    protected $signature = 'lu:openapi
        {path? : Write the document to this file instead of printing it}';

    protected $description = 'Print or write the OpenAPI 3.1 document of the models exposed on the api transport.';

    public function handle(): int
    {
        $json = json_encode(OpenApi::document(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (($path = $this->argument('path')) !== null) {
            if (! is_dir(dirname((string) $path)) || ! is_writable(dirname((string) $path))) {
                $this->components->error('Could not write '.$path.': the directory does not exist or is not writable.');

                return self::FAILURE;
            }
            file_put_contents((string) $path, $json."\n");
            $this->components->info('OpenAPI document written to '.$path);

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
