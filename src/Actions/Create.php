<?php

namespace All1\LuModels\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class Create
{
    use AsAction;

    // pass model, data, and controller_type (web, api, etc)[for record logs and verbs events]
    public function handle($service, $model, $data, $controller_type)
    {

        $action = 'create';
        // AUTHORIZATION
        $service->authorize($action, $model, $data, $controller_type);

        // Fire the Verbs event to handle state change
        $service = $service->fireVerbs('Creation', $service, $model, $data, $controller_type);

        return $service;
    }
}
