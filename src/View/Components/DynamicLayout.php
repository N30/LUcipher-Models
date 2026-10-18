<?php

namespace All1\LuModels\View\Components;

use Illuminate\View\Component;

class DynamicLayout extends Component
{
    public $layout;

    public function __construct($layout)
    {
        $this->layout = $layout;
    }

    public function render()
    {
        return view('lu_models::components.dynamic-layout');
    }
}
