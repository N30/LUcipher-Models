<?php

namespace All1\LuModels\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Records writes. Called by the service after a create, update or delete has happened, with the
 * attributes the model had before the write, so an update can store before and after values.
 * Hidden attributes ($hidden, hidden_columns) are never stored; `audit.store_values` off keeps only
 * the attribute names. A missing table logs a warning and skips, it never blocks the write.
 */
final class AuditLogger
{
    /**
     * @param  'created'|'updated'|'deleted'  $action
     * @param  array<string, mixed>  $before  the model's attributes before the write (empty for creates)
     */
    public static function record(string $action, Model $model, array $before, ?string $transport): ?AuditEntry
    {
        if (! config('lu::models.audit.enabled', false)) {
            return null;
        }

        $table = config('lu::models.audit.table', 'lu_models_audit');
        if (! Schema::connection((new AuditEntry)->getConnectionName())->hasTable($table)) {
            Log::warning("lu_models: audit is enabled but the {$table} table does not exist; run `php artisan migrate`.");

            return null;
        }

        return AuditEntry::create([
            'model_type' => get_class($model),
            'model_id' => $model->getKey() === null ? null : (string) $model->getKey(),
            'action' => $action,
            'transport' => $transport,
            'user_id' => auth()->id(),
            'ip' => app()->bound('request') ? request()->ip() : null,
            'changes' => self::changes($action, $model, $before),
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function changes(string $action, Model $model, array $before): array
    {
        $after = match ($action) {
            'created' => $model->getAttributes(),
            'updated' => $model->getChanges() ?: $model->getAttributes(),
            default => [],
        };
        $before = $action === 'deleted'
            ? ($before ?: $model->getAttributes())
            : array_intersect_key($before, $after);

        $after = self::withoutHidden($model, $after);
        $before = self::withoutHidden($model, $before);

        if (! config('lu::models.audit.store_values', true)) {
            return ['fields' => array_values(array_unique([...array_keys($before), ...array_keys($after)]))];
        }

        return array_filter(['before' => $before, 'after' => $after], fn ($side) => $side !== []);
    }

    /** @param  array<string, mixed>  $values */
    private static function withoutHidden(Model $model, array $values): array
    {
        $hidden = [...$model->getHidden(), ...(array) ($model->hidden_columns ?? [])];

        return array_diff_key($values, array_flip($hidden));
    }
}
