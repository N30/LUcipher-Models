<?php

namespace All1\LuModels\Tests\Fixtures;

use All1\LuModels\Models\LuModel;

/** A model the app overrides: see Fixtures/app-overrides.php. */
class Gadget extends LuModel
{
    protected $table = 'widgets';

    protected $fillable = ['name'];

    public $timestamps = false;
}
