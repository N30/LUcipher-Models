<?php

namespace All1\LuModels\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class Delete
{
    use AsAction;

    public function handle($data)
    {
        dd($data);
        $data->update(request()->all());
    }


}
