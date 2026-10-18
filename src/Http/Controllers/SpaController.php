<?php

namespace All1\LuModels\Http\Controllers;

use All1\LuModels\Livewire\Form;
use All1\LuModels\Livewire\Index;
use All1\LuModels\Livewire\Show;
use All1\LuModels\Services\LuModels;
use All1\LuModels\Support\Ui;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * GET /spa/{model}/{action?}/{id?}: resolve the slug and the action once, here at the entry point, and
 * mount the Livewire component that owns the action with typed parameters (study §6.5). 3.0.x mounted
 * one generic component that re-read its own route and always ran the index.
 */
class SpaController
{
    public const COMPONENTS = [
        'index' => Index::class,
        'create' => Form::class,
        'edit' => Form::class,
        'show' => Show::class,
    ];

    public function __invoke(Request $request): View
    {
        $slug = (string) $request->route('model');
        $action = (string) ($request->route('action') ?? 'index');
        $id = $request->route('id');

        $component = self::COMPONENTS[$action] ?? abort(404, 'No such page');
        if (in_array($action, ['show', 'edit'], true) && $id === null) {
            abort(404, 'Record not found');
        }

        (new LuModels)->controller_initialize('spa', $slug); // 404 for a model that is unknown or not exposed on spa

        return view('lu_models::spa.page', [
            'livewireComponent' => $component,
            'params' => array_filter(['model' => $slug, 'id' => $id], fn ($value) => $value !== null),
            'layout' => Ui::layout(),
        ]);
    }
}
