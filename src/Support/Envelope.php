<?php

namespace All1\LuModels\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The one shape a read comes back in, for the JSON API and the MCP tools alike: `{data, meta, links}`
 * for a page, `{data, meta: {count}}` for a flat list, `{data}` for one record. Records are serialized
 * the way the model serializes itself, so hidden attributes never appear.
 */
final class Envelope
{
    /** @return array<string, mixed> */
    public static function of(mixed $result): array
    {
        if ($result instanceof Paginator) {
            $total = $result instanceof LengthAwarePaginator;

            return self::plain([
                'data' => $result->items(),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'from' => $result->firstItem(),
                    'to' => $result->lastItem(),
                    'per_page' => $result->perPage(),
                    'last_page' => $total ? $result->lastPage() : null,
                    'total' => $total ? $result->total() : null,
                    'path' => $result->path(),
                ],
                'links' => [
                    'first' => $result->url(1),
                    'last' => $total ? $result->url($result->lastPage()) : null,
                    'prev' => $result->previousPageUrl(),
                    'next' => $result->nextPageUrl(),
                ],
            ]);
        }

        if ($result instanceof Collection) {
            return self::plain(['data' => $result->values(), 'meta' => ['count' => $result->count()]]);
        }

        if ($result instanceof Model) {
            return self::plain(['data' => $result]);
        }

        return self::plain(['data' => $result]);
    }

    /**
     * Plain arrays, as JSON would carry them: models become their serialized attributes.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    public static function plain(array $value): array
    {
        return (array) json_decode((string) json_encode($value, JSON_THROW_ON_ERROR), true);
    }
}
