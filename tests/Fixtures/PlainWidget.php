<?php

namespace All1\LuModels\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A plain Eloquent model: it declares none of LuModel's safety flags. */
class PlainWidget extends Model
{
    protected $table = 'widgets';

    protected $fillable = ['name'];

    public $timestamps = false;
}
