<?php

namespace All1\LuModels\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per create, update or delete that went through the package.
 *
 * @property string $model_type
 * @property string|null $model_id
 * @property string $action created|updated|deleted
 * @property string|null $transport web|rest|api|spa
 * @property int|null $user_id
 * @property string|null $ip
 * @property array|null $changes
 */
class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    public function getTable()
    {
        return config('lu::models.audit.table', 'lu_models_audit');
    }
}
