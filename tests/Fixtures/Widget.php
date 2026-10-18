<?php

namespace All1\LuModels\Tests\Fixtures;

use All1\LuModels\Models\LuModel;

/** A LuModel with the default safety flags: only `name` is fillable. */
class Widget extends LuModel
{
    protected $table = 'widgets';

    protected $fillable = ['name'];

    public $timestamps = false;
}
