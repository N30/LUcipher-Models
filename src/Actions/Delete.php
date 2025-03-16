<?php

namespace All1\LuModels\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use All1\LuModels\Verbs\Deletion;
class Delete
{
    use AsAction;
    //pass model, data, and controller_type (web, api, etc)[for record logs and verbs events]
    public function handle($service, $model, $data, $controller_type)
    {

        $action = 'delete';
        //AUTHORIZATION
        $service->authorize($action, $model, $data, $controller_type);

        // Fire the Verbs event to handle state change
        $service = $service->fireVerbs('Deletion', $service, $model, $data, $controller_type);
         
        return $service;
    }

 
}
