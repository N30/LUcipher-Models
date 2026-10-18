<?php

namespace All1\LuModels\Events;

/** Fired after a record was deleted; see ModelEvent for the broadcast contract. */
class Deleted extends ModelEvent
{
    public function action(): string
    {
        return 'deleted';
    }
}
