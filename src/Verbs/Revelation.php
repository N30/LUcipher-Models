<?php

namespace All1\LuModels\Verbs;

use All1\LuModels\Actions\Read;

/** @deprecated 3.1 Adapter over Actions\Read::perform(); see Verb. */
class Revelation extends Verb
{
    protected const ACTION = Read::class;
}
