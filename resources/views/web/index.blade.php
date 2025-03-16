<?php if (!isset($layout)) {
    $layout = config('lu::models.layout'); //config('models.layout');
} else {
    $layout = 'lu_models::empty-layout';
}
?>
<x-lu_models::dynamic-layout :layout="$layout">
 
        
    <flux:button href="{{ route('lu::'.$controller_type.'.resources', [$route_prefix, 'create']) }}" variant="primary" size="sm"
        class="float-end">
        {{ __('Create New') }} {{ __(config('lu::models.lang_prefix') . $route_prefix) }}
    </flux:button>

    <flux:heading size="xl" class="mt-2">
        {{ ucfirst(__(config('lu::models.lang_prefix') . $action)) . ' ' . __(config('lu::models.lang_prefix') . Str::plural($route_prefix)) }}
    </flux:heading>

    <div class="clear-both"></div>

    <flux:separator class="mt-4 mb-6" />
 
    <x-lu_models::index-filters :type="$controller_type" :data="$data" :routePrefix="$route_prefix" :filters="$filters" />

    <flux:separator class="my-4" />
 
    <x-lu_models::index-table :data="$data" :dbColumns="$db_columns" :type="$controller_type" :routePrefix="$route_prefix"   />

    <x-lu_models::web-index-pagination :data="$data" />

    {{--  $data->links() --}}

</x-lu_models::dynamic-layout>
