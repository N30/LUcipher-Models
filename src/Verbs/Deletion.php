<?php

namespace All1\LuModels\Verbs;

use All1\LuModels\Actions\Delete;

/** @deprecated 3.1 Adapter over Actions\Delete::perform(); see Verb. */
class Deletion extends Verb
{
    protected const ACTION = Delete::class;
}
