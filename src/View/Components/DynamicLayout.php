<?php

namespace All1\LuModels\View\Components;

use Illuminate\View\Component;

/**
 * The layout swap (study §2.2): one blade tree, the host layout injected per surface. A page extends
 * the host layout through Blade sections; a fragment (the Livewire components, which already sit inside
 * a page) prints its slot as is. The fragment path must not @extends: a nested @extends shares the
 * section stack with the page around it and replaces the page's content with its own.
 */
class DynamicLayout extends Component
{
    public const EMPTY = 'lu_models::empty-layout';

    public $layout;

    public function __construct($layout)
    {
        $this->layout = $layout;
    }

    public function render()
    {
        return view($this->layout === self::EMPTY ? 'lu_models::components.empty-layout' : 'lu_models::components.dynamic-layout');
    }
}
