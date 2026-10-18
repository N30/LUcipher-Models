<?php
// The same blade is a full page on /web and /rest and a fragment on /spa: the Livewire renderer
// passes a $layout, which means "the empty one" (study §2.2, the layout swap).
$layout = isset($layout) ? 'lu_models::empty-layout' : \All1\LuModels\Support\Ui::layout();
$lang = config('lu::models.lang_prefix');
?>
<x-lu_models::dynamic-layout :layout="$layout">
    <x-lu_models::ui.toasts />

    <div class="lu-models lu-index space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <x-lu_models::ui.heading>
                {{ ucfirst(__($lang.$action)).' '.__($lang.Str::plural($route_prefix)) }}
            </x-lu_models::ui.heading>
            <x-lu_models::ui.button :href="route('lu::'.$controller_type.'.resources', [$route_prefix, 'create'])" variant="primary" size="sm">
                {{ __('Create New') }} {{ __($lang.Str::singular($route_prefix)) }}
            </x-lu_models::ui.button>
        </div>

        <x-lu_models::ui.separator />

        <x-lu_models::index-filters :type="$controller_type" :model="$model" :routePrefix="$route_prefix" :filters="$filters" />

        <x-lu_models::index-table :data="$data" :model="$model" :dbColumns="$db_columns" :type="$controller_type" :routePrefix="$route_prefix" />

        <x-lu_models::ui.pagination :paginator="$data" />
    </div>
</x-lu_models::dynamic-layout>
