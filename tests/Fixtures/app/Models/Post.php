<?php

namespace App\Models;

use All1\LuModels\Models\LuModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $body
 * @property string|null $secret_token
 */
class Post extends LuModel
{
    protected $fillable = ['user_id', 'title', 'body'];

    protected $hidden = ['secret_token'];

    /**
     * Help text per field; wins over the column comment (SQLite keeps none).
     *
     * @var array<string, string>
     */
    public $lu_help = ['title' => 'Shown as the headline'];

    /**
     * body is shown on the detail page and the forms, never in the index listing.
     *
     * @var array<string, list<string>>
     */
    public $lu_fields = ['body' => ['show', 'create', 'edit']];

    /** @var list<string> */
    public $index_columns = ['id', 'title', 'created_at'];

    /** @var array<string, string> */
    public $store_validation_rules = ['title' => 'required|string|max:120'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
