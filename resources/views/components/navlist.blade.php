{{-- The exposed models of one transport, as a navigation list. --}}
@php
    $lang = config('lu::models.lang_prefix');
    $items = [];
    foreach ($iterable as $menu) {
        $items[] = [
            'label' => ucfirst(__($lang.strtolower($menu['name']))),
            'href' => route('lu::'.$type.'.resources', ['model' => $menu['route_group'], 'action' => 'index']),
            'current' => request()->routeIs('lu::'.$type.'.resources*') && request()->route('model') === $menu['route_group'],
        ];
    }
@endphp
@if (\All1\LuModels\Support\Ui::flux())
    <flux:navlist variant="outline">
        <flux:navlist.group :heading="strtoupper($type)" class="grid">
            @foreach ($items as $item)
                <flux:navlist.item :href="$item['href']" :current="$item['current']" wire:navigate>{{ $item['label'] }}</flux:navlist.item>
            @endforeach
        </flux:navlist.group>
    </flux:navlist>
@else
    <nav class="lu-navlist" aria-label="{{ strtoupper($type) }}">
        <p class="px-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ strtoupper($type) }}</p>
        <ul class="mt-1 space-y-0.5">
            @foreach ($items as $item)
                <li><a href="{{ $item['href'] }}" @class(['block rounded-md px-2 py-1.5 text-sm', 'bg-zinc-100 font-medium text-zinc-900' => $item['current'], 'text-zinc-700 hover:bg-zinc-50' => ! $item['current']]) wire:navigate>{{ $item['label'] }}</a></li>
            @endforeach
        </ul>
    </nav>
@endif
