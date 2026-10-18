{{-- A link, or (with a method other than GET) a form that posts it; variant danger for destructive items --}}
@props(['href', 'method' => 'GET', 'variant' => 'default', 'confirm' => null, 'icon' => null])
@php $flux = \All1\LuModels\Support\Ui::flux(); $method = strtoupper($method); @endphp
@if ($method === 'GET')
    @if ($flux)
        <flux:menu.item :href="$href" :icon="$icon" :variant="$variant === 'danger' ? 'danger' : null">{{ $slot }}</flux:menu.item>
    @else
        <a href="{{ $href }}" {{ $attributes->class(['block rounded px-3 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100']) }}>{{ $slot }}</a>
    @endif
@else
    <form method="POST" action="{{ $href }}" @if ($confirm) onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }})" @endif {{ $attributes }}>
        @csrf
        @method($method)
        @if ($flux)
            <flux:menu.item type="submit" :icon="$icon" :variant="$variant === 'danger' ? 'danger' : null">{{ $slot }}</flux:menu.item>
        @else
            <button type="submit" class="block w-full rounded px-3 py-1.5 text-left text-sm {{ $variant === 'danger' ? 'text-red-700 hover:bg-red-50' : 'text-zinc-700 hover:bg-zinc-100' }}">{{ $slot }}</button>
        @endif
    </form>
@endif
