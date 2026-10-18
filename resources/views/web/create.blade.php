<?php
$layout = isset($layout) ? 'lu_models::empty-layout' : \All1\LuModels\Support\Ui::layout();
$lang = config('lu::models.lang_prefix');
$writeType = $controller_type === 'spa' ? 'web' : $controller_type;
?>
<x-lu_models::dynamic-layout :layout="$layout">
    <x-lu_models::ui.toasts />

    <div class="lu-models lu-create space-y-4">
        <x-lu_models::ui.heading>{{ __($lang.ucfirst($action)).' '.__($lang.Str::singular($route_prefix)) }}</x-lu_models::ui.heading>
        <x-lu_models::ui.separator />

        @include('lu_models::web._form', [
            'submit' => route('lu::'.$writeType.'.resources.store', [$route_prefix, 'store']),
            'method' => 'POST',
            'cancel' => route('lu::'.$controller_type.'.resources', [$route_prefix, 'index']),
        ])
    </div>
</x-lu_models::dynamic-layout>
