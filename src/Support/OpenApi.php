<?php

namespace All1\LuModels\Support;

use All1\LuModels\Actions\Create;
use All1\LuModels\Actions\Delete;
use All1\LuModels\Actions\Index;
use All1\LuModels\Actions\Read;
use All1\LuModels\Actions\Update;
use All1\LuModels\Routing\ResourceRoutes;
use Illuminate\Support\Str;

/**
 * The OpenAPI 3.1 document of the REST-shaped API, built from the descriptor of every model exposed
 * on the api transport and from the operations' schema(): the same description the controllers, the
 * forms and the MCP tools read, so the document cannot drift from what the code accepts.
 */
final class OpenApi
{
    public const VERSION = '3.1.0';

    public const DIALECT = 'https://json-schema.org/draft/2020-12/schema';

    /** @return array<string, mixed> */
    public static function document(): array
    {
        $paths = [];
        $tags = [];
        $schemas = self::sharedSchemas();

        foreach (array_keys(Exposure::exposedModels('api')) as $class) {
            $d = Descriptor::for($class);
            $name = $d->name;
            $tags[] = ['name' => $name, 'description' => $d->headline.' ('.$class.')'];

            $schemas[$name] = self::recordSchema($d, 'show');
            $schemas[$name.'Summary'] = self::recordSchema($d, 'index');
            $schemas[$name.'Create'] = (new Create)->schema($d);
            $schemas[$name.'Update'] = (new Update)->schema($d);

            $collection = [];
            if ((new Index)->supports($d, 'api')) {
                $collection['get'] = self::listOperation($d);
            }
            if ((new Create)->supports($d, 'api')) {
                $collection['post'] = self::writeOperation($d, 'create', 'Create a '.$name, $name.'Create');
            }
            if ($collection !== []) {
                $paths['/'.$d->slug] = $collection;
            }

            $member = ['parameters' => [self::keyParameter()]];
            if ((new Read)->supports($d, 'api')) {
                $member['get'] = self::showOperation($d);
            }
            if ((new Update)->supports($d, 'api')) {
                $member['patch'] = self::writeOperation($d, 'update', 'Update a '.$name.' (partial)', $name.'Update');
                $member['put'] = self::writeOperation($d, 'update', 'Update a '.$name, $name.'Update');
            }
            if ((new Delete)->supports($d, 'api')) {
                $member['delete'] = self::deleteOperation($d);
            }
            if (count($member) > 1) {
                $paths['/'.$d->slug.'/{id}'] = $member;
            }
        }

        return [
            'openapi' => self::VERSION,
            'jsonSchemaDialect' => self::DIALECT,
            'info' => [
                'title' => config('lu::models.openapi.title') ?? config('app.name', 'Laravel').' API',
                'version' => (string) config('lu::models.openapi.version', '1.0.0'),
                'description' => 'The models this application exposes through lucipher/models. Every operation is authorized by the model\'s policy; a write accepts dry_run to be checked without being made.',
            ],
            'servers' => [['url' => url('/'.ResourceRoutes::apiPrefix())]],
            'security' => [['sanctum' => []]],
            'tags' => $tags,
            'paths' => $paths,
            'components' => [
                'securitySchemes' => ['sanctum' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'A Laravel Sanctum token']],
                'schemas' => $schemas,
            ],
        ];
    }

    /**
     * The fields a reader of $action sees, as a JSON Schema: the column's type and length, or the
     * length the create rules enforce where the driver reports none. No `required`: sparse `fields`
     * may omit any of them, and `include` may add relationships.
     *
     * @return array<string, mixed>
     */
    private static function recordSchema(Descriptor $d, string $action): array
    {
        $properties = [];
        foreach ($d->fieldsFor($action) as $field => $description) {
            $properties[$field] = Descriptor::jsonSchemaFor($description, $d->rules['create'][$field] ?? []);
        }

        return [
            'type' => 'object',
            'title' => $d->headline.($action === 'index' ? ' (listing)' : ''),
            'description' => 'A '.$d->name.' as the '.$action.' action shows it; `fields` narrows it, `include` adds relationships.',
            'properties' => $properties,
            'additionalProperties' => true,
        ];
    }

    /** @return array<string, mixed> */
    private static function listOperation(Descriptor $d): array
    {
        $plural = Str::plural($d->name);
        $parameters = [];
        foreach ((new Index)->schema($d)['properties'] as $name => $schema) {
            $parameter = ['name' => $name, 'in' => 'query', 'schema' => $schema];
            if (isset($schema['description'])) {
                $parameter['description'] = $schema['description'];
            }
            if ($name === 'filter') {
                $parameter['style'] = 'deepObject';
                $parameter['explode'] = true;
            } elseif (in_array('array', (array) ($schema['type'] ?? []), true)) {
                $parameter['style'] = 'form';
                $parameter['explode'] = true;
            }
            $parameters[] = $parameter;
        }

        return [
            'operationId' => 'list'.$plural,
            'tags' => [$d->name],
            'summary' => 'List '.$plural,
            'description' => 'A page of '.$plural.' ({data, meta, links}); with `limit`, a flat list ({data, meta: {count}}). Unknown or unreadable names in filter, sort, fields or include are a 400 naming them.',
            'parameters' => $parameters,
            'responses' => [
                '200' => self::jsonResponse('The page', [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'array', 'items' => self::ref($d->name.'Summary')],
                        'meta' => self::ref('PageMeta'),
                        'links' => self::ref('PageLinks'),
                    ],
                    'required' => ['data', 'meta'],
                ]),
                '400' => self::errorResponse('A name the action may not read, or a bad operator'),
                '403' => self::errorResponse('The policy refused the listing'),
                '429' => self::errorResponse('Rate limited'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function showOperation(Descriptor $d): array
    {
        return [
            'operationId' => 'show'.$d->name,
            'tags' => [$d->name],
            'summary' => 'Show one '.$d->name,
            'parameters' => [
                ['name' => 'fields', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Comma-separated field names to return'],
                ['name' => 'include', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Comma-separated relationships to include'],
            ],
            'responses' => [
                '200' => self::jsonResponse('The record', ['type' => 'object', 'properties' => ['data' => self::ref($d->name)], 'required' => ['data']]),
                '403' => self::errorResponse('The policy refused, or a guest used a plain key'),
                '404' => self::errorResponse('No such record'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function writeOperation(Descriptor $d, string $action, string $summary, string $bodySchema): array
    {
        return [
            'operationId' => $action.$d->name,
            'tags' => [$d->name],
            'summary' => $summary,
            'parameters' => [self::dryRunParameter()],
            'requestBody' => [
                'required' => true,
                'content' => ['application/json' => ['schema' => self::ref($bodySchema)]],
            ],
            'responses' => [
                '200' => self::jsonResponse('The record as written, or what a dry run would write', ['oneOf' => [self::writtenSchema($d), self::ref('DryRun')]]),
                '403' => self::errorResponse('The policy refused'),
                '404' => self::errorResponse('No such record'),
                '422' => self::jsonResponse('The input was refused, by field; a key the model does not allow written is refused too', self::ref('ValidationError')),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function deleteOperation(Descriptor $d): array
    {
        return [
            'operationId' => 'delete'.$d->name,
            'tags' => [$d->name],
            'summary' => 'Delete a '.$d->name,
            'parameters' => [self::dryRunParameter()],
            'responses' => [
                '200' => self::jsonResponse('The deleted record, or what a dry run would delete', ['oneOf' => [self::writtenSchema($d), self::ref('DryRun')]]),
                '403' => self::errorResponse('The policy refused'),
                '404' => self::errorResponse('No such record'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function writtenSchema(Descriptor $d): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
                'data' => self::ref($d->name),
                'id' => ['type' => 'string', 'description' => 'The encrypted key of the record written'],
            ],
            'required' => ['message', 'data'],
        ];
    }

    /** @return array<string, mixed> */
    private static function keyParameter(): array
    {
        return [
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => ['integer', 'string']],
            'description' => 'The record key: plain, encrypted, or a slug where the model has one. A guest may only use the encrypted key unless the model opts in.',
        ];
    }

    /** @return array<string, mixed> */
    private static function dryRunParameter(): array
    {
        return [
            'name' => 'dry_run',
            'in' => 'query',
            'schema' => ['type' => 'boolean'],
            'description' => 'Authorize and validate only: nothing is written and the answer is in the conditional ({would: true}).',
        ];
    }

    /** @return array<string, mixed> */
    private static function sharedSchemas(): array
    {
        $string = ['type' => 'string'];
        $nullableInt = ['type' => ['integer', 'null']];

        return [
            'Error' => ['type' => 'object', 'properties' => ['message' => $string], 'required' => ['message']],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'message' => $string,
                    'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => $string]],
                ],
                'required' => ['message', 'errors'],
            ],
            'PageMeta' => [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer'],
                    'from' => $nullableInt,
                    'to' => $nullableInt,
                    'per_page' => ['type' => 'integer'],
                    'last_page' => $nullableInt,
                    'total' => $nullableInt,
                    'path' => $string,
                    'count' => ['type' => 'integer', 'description' => 'Only on a flat `limit` list'],
                ],
            ],
            'PageLinks' => [
                'type' => 'object',
                'properties' => [
                    'first' => ['type' => ['string', 'null']],
                    'last' => ['type' => ['string', 'null']],
                    'prev' => ['type' => ['string', 'null']],
                    'next' => ['type' => ['string', 'null']],
                ],
            ],
            'DryRun' => [
                'type' => 'object',
                'description' => 'What a write would do: authorized and validated, nothing written.',
                'properties' => [
                    'would' => ['const' => true],
                    'message' => ['type' => 'string', 'description' => 'In the conditional, e.g. "Would create Post"'],
                    'data' => ['type' => 'object', 'description' => 'The attributes that would be written'],
                ],
                'required' => ['would', 'message'],
            ],
        ];
    }

    /** @return array<string, string> */
    private static function ref(string $schema): array
    {
        return ['$ref' => '#/components/schemas/'.$schema];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function jsonResponse(string $description, array $schema): array
    {
        return ['description' => $description, 'content' => ['application/json' => ['schema' => $schema]]];
    }

    /** @return array<string, mixed> */
    private static function errorResponse(string $description): array
    {
        return self::jsonResponse($description, self::ref('Error'));
    }
}
