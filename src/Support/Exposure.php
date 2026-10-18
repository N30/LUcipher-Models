<?php

namespace All1\LuModels\Support;

use All1\LuModels\Models\LuModel;

/**
 * Which models a transport may serve.
 *
 * Nothing is exposed until the app says so, in one of two places: `config('lu::models.models')`
 * (class => transports, or `true` for all of them) or the model itself (`public $lu_transports`).
 * The two are a union. `config('lu::models.expose_all')` exposes every discovered model and is a
 * development convenience; policies are still required either way (see LuModels::authorize()).
 */
final class Exposure
{
    public const TRANSPORTS = ['web', 'rest', 'api', 'spa'];

    public static function isExposed(string|object $model, string $transport): bool
    {
        return in_array($transport, self::transportsFor($model), true);
    }

    /** @return list<string> */
    public static function transportsFor(string|object $model): array
    {
        if (config('lu::models.expose_all', false)) {
            return self::TRANSPORTS;
        }

        $class = self::normalize(is_object($model) ? get_class($model) : $model);
        $transports = [];

        foreach ((array) config('lu::models.models', []) as $configured => $allowed) {
            if (self::normalize((string) $configured) === $class) {
                $transports = $allowed === true ? self::TRANSPORTS : array_values((array) $allowed);
            }
        }

        $instance = is_object($model) ? $model : (class_exists($class) ? new $class : null);
        if ($instance !== null && isset($instance->lu_transports)) {
            $transports = array_merge($transports, array_values((array) $instance->lu_transports));
        }

        return array_values(array_unique(array_intersect($transports, self::TRANSPORTS)));
    }

    /**
     * The discovered models exposed on $transport, keyed by class, with LuModel::getMetaData() values.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function exposedModels(string $transport): array
    {
        return array_filter(LuModel::getModels(), fn ($meta, $class) => self::isExposed($class, $transport), ARRAY_FILTER_USE_BOTH);
    }

    private static function normalize(string $class): string
    {
        return ltrim(str_replace('/', '\\', $class), '\\');
    }
}
