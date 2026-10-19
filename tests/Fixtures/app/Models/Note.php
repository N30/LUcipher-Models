<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A plain Eloquent model that exposes itself on one transport by declaration. */
class Note extends Model
{
    protected $fillable = ['body'];

    /** @var list<string> */
    public $lu_transports = ['api'];
}
