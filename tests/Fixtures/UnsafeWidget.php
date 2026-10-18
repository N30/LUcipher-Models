<?php

namespace All1\LuModels\Tests\Fixtures;

use All1\LuModels\Models\LuModel;

/** A LuModel that explicitly opts out of both write safeties. */
class UnsafeWidget extends LuModel
{
    protected $table = 'widgets';

    protected $fillable = ['name'];

    public $timestamps = false;

    public $creation_safety = false;

    public $update_safety = false;
}
