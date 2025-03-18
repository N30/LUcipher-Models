<form 
    x-data="{
        needs_submit: false,
    }" 
    action="{{ route('lu::web.resources', [$routePrefix, 'index']) }}" method="GET">
    @method('GET')

    <div class="grid auto-rows-min gap-4 md:grid-cols-4">
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-lu_models::filter-relationships :model="$data[0]" :type="$type" :filters="$filters" />
        </div>
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
           
        </div>
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
           
        </div>
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-lu_models::filter-search :model="$data[0]" :livewire=false :filters="$filters" />
        </div>
    </div>

    <flux:button x-show="needs_submit" type="submit" size="sm" variant="primary" class="mt-4">
        {{ __('Apply Filters') }}
    </flux:button>

</form>