<?php

namespace All1\LuModels\Verbs;

use All1\LuModels\Actions\Update;

/** @deprecated 3.1 Adapter over Actions\Update::perform(); see Verb. */
class Updation extends Verb
{
    protected const ACTION = Update::class;
}
