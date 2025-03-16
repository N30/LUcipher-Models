<?php

namespace All1\LuModels\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use All1\LuModels\Verbs\Revelation;
 
class Read
{
    use AsAction;
    //pass model, data, and controller_type (web, api, etc)[for record logs and verbs events]
    public function handle($service, $model, $data, $controller_type)
    {

        $action = 'show';
        //AUTHORIZATION
        $service->authorize($action, $model, $data, $controller_type);

        // Fire the Verbs event to handle state change
        $model = $service = $service->fireVerbs('Revelation', $service, $model, $data, $controller_type);
         
        return $service;
    }

 
}

class Show extends Read {}
