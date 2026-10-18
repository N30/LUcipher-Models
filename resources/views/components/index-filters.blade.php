{{-- The listing's filters: a GET form on /web and /rest; on /spa the wire: attributes make it live. --}}
@php
    $relationships = array_keys(\All1\LuModels\Support\Descriptor::for($model)->relationships);
    $include = (array) ($filters['include'] ?? $filters['with'] ?? []);
@endphp
<form class="lu-filters flex flex-wrap items-end gap-3" action="{{ route('lu::'.$type.'.resources', [$routePrefix, 'index']) }}" method="GET" wire:submit.prevent>
    @foreach (['sort', 'order', 'per_page'] as $carry)
        @if (! empty($filters[$carry]))
            <input type="hidden" name="{{ $carry }}" value="{{ $filters[$carry] }}" />
        @endif
    @endforeach

    <div class="min-w-56 grow">
        <x-lu_models::ui.input name="search" :label="__('Search')" :value="$filters['search'] ?? null" :placeholder="__('Search keyword…')" wire:model.live.debounce.400ms="search" />
    </div>

    @if ($relationships !== [])
        <div class="min-w-48">
            <x-lu_models::ui.select name="include" :label="__('Include')" :options="array_combine($relationships, array_map(fn ($r) => Str::headline($r), $relationships))" :selected="$include" :multiple="true" wire:model.live="include" />
        </div>
    @endif

    <x-lu_models::ui.button type="submit" size="sm">{{ __('Apply') }}</x-lu_models::ui.button>
    @if (($filters['search'] ?? '') !== '' || $include !== [] || ! empty($filters['sort']))
        <x-lu_models::ui.button :href="route('lu::'.$type.'.resources', [$routePrefix, 'index'])" size="sm" variant="ghost" wire:click.prevent="resetFilters">{{ __('Reset') }}</x-lu_models::ui.button>
    @endif
</form>
