<?php

namespace App\Extra;

/** Opts out of the create safety: every submitted key is written, fillable or not. */
class UnsafeEvent extends Event
{
    protected $fillable = ['title'];

    /** @var bool */
    public $creation_safety = false;
}
