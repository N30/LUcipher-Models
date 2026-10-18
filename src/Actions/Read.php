<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Events\Read as ReadEvent;
use All1\LuModels\Services\LuModels;

/** Show one record: the service's show query, then the Read event. The policy ability is "show". */
class Read extends Action
{
    public const VERB = 'Revelation';

    public function name(): string
    {
        return 'show';
    }

    public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        $service->controller_show($model);

        $this->dispatchEvent($model, 'Read', ReadEvent::class, $data, $controller_type);

        return $service;
    }
}
