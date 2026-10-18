<?php

namespace All1\LuModels\Support;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * What every write action hands its transport: the record it produced, a message for a human, the
 * validation errors when it was refused, an HTTP status and, for a created record, its encrypted key.
 *
 * The transports only decide how this is spelled: JSON (API), toast + redirect (web), flash + back
 * (rest), toast (Livewire). 3.0.x passed an untyped array whose shape varied per action and transport.
 */
final class Result
{
    private function __construct(
        public readonly string $message,
        public readonly mixed $data,
        public readonly ?Model $model,
        public readonly ?MessageBag $errors,
        public readonly int $status,
        public readonly ?string $id,
    ) {}

    /** A write that happened. $id is the encrypted key a redirect may use; defaults to the record's. */
    public static function ok(string $message, ?Model $record = null, mixed $data = null, ?string $id = null): self
    {
        return new self($message, $data ?? $record, $record, null, 200, $id);
    }

    /** Input the model refused: a validator that failed, or its message bag. 422. */
    public static function invalid(Validator|MessageBag $errors, string $message = 'There was an issue with your input.'): self
    {
        $bag = $errors instanceof Validator ? $errors->errors() : $errors;

        return new self($message, [], null, $bag, 422, null);
    }

    /** The record the request named does not exist. 404. */
    public static function notFound(string $message = 'Record not found'): self
    {
        return new self($message, [], null, null, 404, null);
    }

    public function failed(): bool
    {
        return $this->errors !== null;
    }

    public function found(): bool
    {
        return $this->status !== 404;
    }

    /** The key a redirect after this write should carry: the encrypted id when known, else the record's own key. */
    public function redirectKey(): mixed
    {
        return $this->id ?? $this->model?->getKey();
    }

    public function toJsonResponse(): JsonResponse
    {
        if ($this->failed()) {
            return new JsonResponse(['message' => $this->message, 'errors' => $this->errors->toArray()], $this->status);
        }

        $payload = ['message' => $this->message, 'data' => $this->data];
        if ($this->id !== null) {
            $payload['id'] = $this->id;
        }

        return new JsonResponse($payload, $this->status);
    }

    /** The 3.0.x array shape, for code that still reads $result['message']. */
    public function toArray(): array
    {
        return array_filter([
            'message' => $this->message,
            'data' => $this->data,
            'id' => $this->id,
            'errors' => $this->errors?->toArray(),
        ], fn ($value) => $value !== null);
    }
}
