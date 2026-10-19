<?php

namespace Workbench\App\Models;

use All1\LuModels\Concerns\OwnedByUser;
use All1\LuModels\Models\LuModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A model described once. Everything else in the examples app (the pages, the API, the OpenAPI
 * document, the Livewire UI, the MCP tools) follows from this file and PostPolicy.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $body
 * @property bool $published
 * @property string|null $internal_note
 */
class Post extends LuModel
{
    use OwnedByUser;

    protected $fillable = ['title', 'body', 'published'];

    protected $hidden = ['internal_note']; // hidden never reads out, on any transport

    protected $casts = ['published' => 'boolean'];

    public $lu_help = [
        'title' => 'Shown as the headline everywhere',
        'body' => 'Markdown is fine',
        'published' => 'Drafts are listed too; only you can see yours',
    ];

    /** body is for the detail page and the forms; the listing stays narrow. */
    public $lu_fields = ['body' => ['show', 'create', 'edit']];

    public $index_columns = ['id', 'title', 'published', 'created_at'];

    public $store_validation_rules = ['title' => 'required|string|max:120', 'body' => 'nullable|string', 'published' => 'boolean'];

    public $update_validation_rules = ['title' => 'string|max:120', 'body' => 'nullable|string', 'published' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
