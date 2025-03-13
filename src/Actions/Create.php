<?php

namespace All1\LuModels\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class Create
{
    use AsAction;

    public function handle($data)
    {
        dd($data);
        $data->create(request()->all());
    }

 
}
