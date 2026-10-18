<?php

namespace All1\LuModels\Verbs;

use All1\LuModels\Actions\Create;

/** @deprecated 3.1 Adapter over Actions\Create::perform(); see Verb. */
class Creation extends Verb
{
    protected const ACTION = Create::class;
}
