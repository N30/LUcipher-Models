<?php
$layout = isset($layout) ? 'lu_models::empty-layout' : \All1\LuModels\Support\Ui::layout();
$lang = config('lu::models.lang_prefix');
$writeType = $controller_type === 'spa' ? 'web' : $controller_type;
?>
<x-lu_models::dynamic-layout :layout="$layout">
    <x-lu_models::ui.toasts />

    <div class="lu-models lu-edit space-y-4">
        <x-lu_models::ui.heading>{{ __($lang.ucfirst($action)).' '.__($lang.Str::singular($route_prefix)).' #'.$model->getKey() }}</x-lu_models::ui.heading>
        <x-lu_models::ui.separator />

        @include('lu_models::web._form', [
            'submit' => route('lu::'.$writeType.'.resources.update', [$route_prefix, 'update', $model->getKey()]),
            'method' => 'PUT',
            'cancel' => route('lu::'.$controller_type.'.resources', [$route_prefix, 'show', $model->getKey()]),
        ])
    </div>
</x-lu_models::dynamic-layout>
