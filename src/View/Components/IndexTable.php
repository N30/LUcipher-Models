<?php

namespace All1\LuModels\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IndexTable extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct($data, $dbColumns, $routePrefix, $type, $livewire = false)
    {
        $this->data = $data;
        $this->type= $type;
        $this->dbColumns  = $dbColumns;
        $this->route_prefix = $routePrefix;
        $this->livewire = $livewire;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        if(!is_array($this->data) && $this->data->hasPages()) {
            $hasPage = $this->data->hasPages();
        } else {
            $hasPage = 0;
        }
 
        return view('lu_models::index-table')
            ->with('data', $this->data)
            ->with('db_columns', $this->dbColumns)
            ->with('route_prefix', $this->route_prefix)
            ->with('type', $this->type)
            ->with('has_page', $hasPage);
    }
}
