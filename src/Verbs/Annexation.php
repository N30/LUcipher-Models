<?php

namespace All1\LuModels\Verbs;

use All1\LuModels\Actions\Index;

/** @deprecated 3.1 Adapter over Actions\Index::perform(); see Verb. */
class Annexation extends Verb
{
    protected const ACTION = Index::class;
}
