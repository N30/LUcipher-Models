<?php
$layout = isset($layout) ? 'lu_models::empty-layout' : \All1\LuModels\Support\Ui::layout();
$lang = config('lu::models.lang_prefix');
$writeType = $controller_type === 'spa' ? 'web' : $controller_type;
?>
<x-lu_models::dynamic-layout :layout="$layout">
    <x-lu_models::ui.toasts />

    <div class="lu-models lu-show space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <x-lu_models::ui.heading>
                {{ __($lang.ucfirst(Str::singular($route_prefix))).' #'.$model->getKey() }}
            </x-lu_models::ui.heading>
            <div class="flex gap-2">
                <x-lu_models::ui.button :href="route('lu::'.$controller_type.'.resources', [$route_prefix, 'index'])" size="sm" variant="ghost">{{ __('Back') }}</x-lu_models::ui.button>
                <x-lu_models::ui.button :href="route('lu::'.$controller_type.'.resources', [$route_prefix, 'edit', $model->getKey()])" size="sm" variant="primary">{{ __('Edit') }}</x-lu_models::ui.button>
            </div>
        </div>

        <x-lu_models::ui.separator />

        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ($columns as $c)
                @php $value = $model->getAttribute($c['Field']); @endphp
                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ \All1\LuModels\Support\Ui::label($c['Field']) }}</dt>
                    <dd class="mt-1 text-sm text-zinc-800">
                        @if ($value === null)
                            <span class="text-zinc-400">—</span>
                        @elseif ($c['input_type'] === 'checkbox')
                            {{ $value ? '✓' : '—' }}
                        @elseif ($value instanceof \DateTimeInterface)
                            {{ $value->format('Y-m-d H:i') }}
                        @elseif ($c['input_type'] === 'textarea')
                            <div class="whitespace-pre-wrap">{{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}</div>
                        @else
                            {{ is_array($value) ? json_encode($value) : $value }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>

        @if ($model->getRelations() !== [])
            <x-lu_models::ui.heading size="lg" level="2">{{ __('Relationships') }}</x-lu_models::ui.heading>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($model->getRelations() as $relation => $items)
                    <div class="rounded-lg border border-zinc-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-zinc-700">{{ \All1\LuModels\Support\Ui::label($relation) }}</h3>
                        <ul class="mt-2 flex flex-wrap gap-1">
                            @forelse (is_iterable($items) ? $items : array_filter([$items]) as $item)
                                <li><x-lu_models::ui.badge>{{ $item->name ?? $item->title ?? ('#'.$item->getKey()) }}</x-lu_models::ui.badge></li>
                            @empty
                                <li class="text-sm text-zinc-400">{{ __('None') }}</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end">
            <x-lu_models::ui.menu-item :href="route('lu::'.$writeType.'.resources.destroy', [$route_prefix, 'destroy', $model->getKey()])" method="DELETE" variant="danger" :confirm="__('Delete this record?')" class="inline-block">{{ __('Delete') }}</x-lu_models::ui.menu-item>
        </div>
    </div>
</x-lu_models::dynamic-layout>
