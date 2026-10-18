{{-- The listing. $columns and $headers come from the component class: index_columns when the model names them, else what the index may read. --}}
@php $writeType = $type === 'spa' ? 'web' : $type; @endphp
<x-lu_models::ui.table :columns="$headers" class="lu-index-table">
    @forelse ($data as $row)
        @php $key = $row->getKey(); @endphp
        <x-lu_models::ui.row wire:key="row-{{ $key }}">
            @foreach ($columns as $column)
                <x-lu_models::ui.cell>
                    @if (str_contains($column, '.'))
                        @foreach ($relatedValues($row, $column) as $value)
                            <x-lu_models::ui.badge>{{ $value }}</x-lu_models::ui.badge>
                        @endforeach
                    @elseif (method_exists($row, 'displayText'))
                        {{ $row->displayText($column) }}
                    @else
                        {{ $cellText($row->getAttribute($column)) }}
                    @endif
                </x-lu_models::ui.cell>
            @endforeach
            <x-lu_models::ui.cell class="text-right">
                <x-lu_models::ui.menu>
                    <x-lu_models::ui.menu-item :href="route('lu::'.$type.'.resources', [$route_prefix, 'show', $key])" icon="eye">{{ __('Show') }}</x-lu_models::ui.menu-item>
                    <x-lu_models::ui.menu-item :href="route('lu::'.$type.'.resources', [$route_prefix, 'edit', $key])" icon="pencil">{{ __('Edit') }}</x-lu_models::ui.menu-item>
                    <x-lu_models::ui.menu-item :href="route('lu::'.$writeType.'.resources.destroy', [$route_prefix, 'destroy', $key])" method="DELETE" variant="danger" icon="trash" :confirm="__('Delete this record?')" wire:submit.prevent="delete({{ \Illuminate\Support\Js::from($key) }})">{{ __('Delete') }}</x-lu_models::ui.menu-item>
                </x-lu_models::ui.menu>
            </x-lu_models::ui.cell>
        </x-lu_models::ui.row>
    @empty
        <x-lu_models::ui.row>
            <x-lu_models::ui.cell colspan="{{ count($columns) + 1 }}">
                <span class="text-zinc-500">{{ __('Nothing here yet.') }}</span>
            </x-lu_models::ui.cell>
        </x-lu_models::ui.row>
    @endforelse
</x-lu_models::ui.table>
