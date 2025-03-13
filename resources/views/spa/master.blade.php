<div>
    {{-- Knowing others is intelligence; knowing yourself is true wisdom. --}}
   
    <flux:button href="{{ route('lu::web.resources', [$route_prefix, 'create']) }}" variant="primary" size="sm"
        class="float-end">
        {{ __('Create New') }} {{ __(config('lu::models.lang_prefix') . $route_prefix) }}
    </flux:button>

    <flux:heading size="xl" class="mt-2">
        {{ ucfirst(__(config('lu::models.lang_prefix') . $action)) . ' ' . __(config('lu::models.lang_prefix') . Str::plural($route_prefix)) }}
    </flux:heading>

    <div class="clear-both"></div>

    <flux:separator class="mt-4 mb-6" />

    <x-lu_models::index-filters :data="$data" :routePrefix="$route_prefix" :type="$controller_type" :filters="$filters" />

    <flux:separator class="my-4" />
 
    <x-lu_models::index-table :data="$data" :dbColumns="$db_columns" :type="$controller_type" :routePrefix="$route_prefix"   />

    
</div>
