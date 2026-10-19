<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A plain Eloquent model, not a LuModel: the package works the same, from $fillable, $hidden and the
 * policy. It is exposed on web, api and spa only (see WorkbenchServiceProvider), so agents never see it.
 *
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property string $body
 */
class Comment extends Model
{
    protected $fillable = ['post_id', 'body'];

    public $store_validation_rules = ['post_id' => 'required|integer|exists:posts,id', 'body' => 'required|string|max:2000'];

    public $update_validation_rules = ['body' => 'string|max:2000'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
