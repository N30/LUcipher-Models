<?php

namespace App\Models;

use All1\LuModels\Models\LuModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends LuModel
{
    protected $fillable = ['user_id', 'title', 'body'];

    protected $hidden = ['secret_token'];

    /** Help text per field; wins over the column comment (SQLite keeps none). */
    public $lu_help = ['title' => 'Shown as the headline'];

    /** body is shown on the detail page and the forms, never in the index listing. */
    public $lu_fields = ['body' => ['show', 'create', 'edit']];

    public $index_columns = ['id', 'title', 'created_at'];

    public $store_validation_rules = ['title' => 'required|string|max:120'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
