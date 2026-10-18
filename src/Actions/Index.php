<?php

namespace All1\LuModels\Actions;

use All1\LuModels\Events\Index as IndexEvent;
use All1\LuModels\Services\LuModels;

/** List records: the service's index query, then the Index event. */
class Index extends Action
{
    public const VERB = 'Annexation';

    public function name(): string
    {
        return 'index';
    }

    public function perform(LuModels $service, $model, array $data, ?string $controller_type): LuModels
    {
        $service->controller_index($model);

        $this->dispatchEvent($model, 'Index', IndexEvent::class, $data, $controller_type);

        return $service;
    }
}
