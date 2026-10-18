<?php

namespace All1\LuModels\Events;

/** Fired after a record was updated; see ModelEvent for the broadcast contract. */
class Updated extends ModelEvent
{
    public function action(): string
    {
        return 'updated';
    }
}
