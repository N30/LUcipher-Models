<?php

namespace App\Models;

use All1\LuModels\Models\LuModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends LuModel
{
    protected $fillable = ['user_id', 'title', 'body'];

    public $index_columns = ['id', 'title', 'created_at'];

    public $store_validation_rules = ['title' => 'required|string|max:120'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
