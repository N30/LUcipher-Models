<?php

namespace App\Models;

use All1\LuModels\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

/** A model owned by whoever creates it. */
class Memo extends Model
{
    use OwnedByUser;

    protected $fillable = ['body'];
}
