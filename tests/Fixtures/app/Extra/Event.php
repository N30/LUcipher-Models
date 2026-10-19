<?php

namespace App\Extra;

use All1\LuModels\Models\LuModel;

/**
 * Outside models_path on purpose (it is not discovered): one column of every kind the forms, the table
 * and the writer branch on, a slug the writer fills, and the two injected request columns.
 */
class Event extends LuModel
{
    protected $table = 'events';

    protected $fillable = ['title', 'starts_on', 'starts_at', 'at', 'seats', 'free', 'contact_email', 'meta', 'ip_address', 'user_agent', 'slug'];

    protected $casts = ['starts_on' => 'date', 'starts_at' => 'datetime', 'free' => 'boolean', 'meta' => 'array'];

    /** @var array<string, string> */
    public $store_validation_rules = ['title' => 'required|string|max:80'];

    /** The shape spatie/laravel-sluggable gives: the writer reads generateSlugFrom[0]. */
    public function getSlugOptions(): object
    {
        return (object) ['generateSlugFrom' => ['title']];
    }
}
