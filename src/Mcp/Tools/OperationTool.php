<?php

namespace All1\LuModels\Mcp\Tools;

use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Descriptor;
use All1\LuModels\Support\Envelope;
use All1\LuModels\Support\Keys;
use All1\LuModels\Support\Result;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * One operation of one model as an MCP tool. The name is `{slug}_{verb}`, the input schema is the
 * operation's schema() (what the API and the forms accept), and the brain's refusals (403, 404, 400,
 * 422) come back as tool errors an agent can read instead of protocol errors.
 */
abstract class OperationTool extends Tool
{
    public const VERB = '';

    public function __construct(public readonly Descriptor $descriptor)
    {
        $this->name = Str::slug($descriptor->slug, '_').'_'.static::VERB;
        $this->title = $descriptor->headline.': '.static::VERB;
        $this->description = $this->describe();
    }

    abstract protected function describe(): string;

    /** @return array<string, mixed> */
    abstract public function inputSchema(): array;

    abstract public function handle(Request $request): Response|ResponseFactory;

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['inputSchema'] = $this->inputSchema();

        return $array;
    }

    protected function model(): Model
    {
        return new $this->descriptor->class;
    }

    protected function service(string $action): LuModels
    {
        return LuModels::forTransport('mcp', $this->descriptor->class, $action);
    }

    /** The record the arguments name, by plain key, encrypted key or slug; 404 when none. */
    protected function record(Request $request): Model
    {
        return Keys::find($this->model(), $request->get($this->descriptor->key), guestOptIns: true)
            ?? abort(404, 'No '.$this->descriptor->name.' matches '.json_encode($request->get($this->descriptor->key)).'.');
    }

    /** Run the operation; a refusal the brain raises becomes a tool error. */
    protected function guarded(callable $callback): Response|ResponseFactory
    {
        try {
            return $callback();
        } catch (HttpExceptionInterface $e) {
            return Response::error($e->getStatusCode().': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
        }
    }

    /** A write's Result as the tool's answer: 422 as an error, a dry run as `would`, a write as the record. */
    protected function result(Result $result): Response|ResponseFactory
    {
        if ($result->failed()) {
            return Response::error('422: '.$result->message.' '.json_encode($result->errors?->toArray(), JSON_UNESCAPED_SLASHES));
        }
        if (! $result->found()) {
            return Response::error('404: '.$result->message);
        }

        return Response::structured(array_filter([
            'would' => $result->would ?: null,
            'message' => $result->message,
            'data' => Envelope::plain(['data' => $result->data])['data'],
            'id' => $result->id,
        ], fn ($value) => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function withDryRun(array $schema): array
    {
        $schema['properties']['dry_run'] = [
            'type' => 'boolean',
            'default' => false,
            'description' => 'Authorize and validate only: nothing is written and the answer is in the conditional ("Would …").',
        ];

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function withKey(array $schema, bool $required = true): array
    {
        $schema['properties'] = [$this->descriptor->key => ['type' => ['integer', 'string'], 'description' => 'The record key, plain or encrypted (or its slug)']] + $schema['properties'];
        if ($required) {
            $schema['required'] = array_values(array_unique([$this->descriptor->key, ...($schema['required'] ?? [])]));
        }

        return $schema;
    }
}
