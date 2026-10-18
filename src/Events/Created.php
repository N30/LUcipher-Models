<?php

namespace All1\LuModels\Events;

/** Fired after a record was created; see ModelEvent for the broadcast contract. */
class Created extends ModelEvent
{
    public function action(): string
    {
        return 'created';
    }
}
