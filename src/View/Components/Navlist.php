<?php

namespace All1\LuModels\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Navlist extends Component
{

    public $type;
    /**
     * Create a new component instance.
     */
    public function __construct( $type='spa')
    {
        $this->type = $type;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $list = \All1\LuModels\Models\LuModel::getModels();
        return view('lu_models::navlist')
            ->with('type',$this->type)
            ->with('iterable',$list);
    }
}
