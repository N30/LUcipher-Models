<?php

namespace All1\LuModels\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
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