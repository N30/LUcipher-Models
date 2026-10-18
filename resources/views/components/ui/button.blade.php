@props(['variant' => 'default', 'size' => 'base', 'href' => null, 'type' => 'button', 'icon' => null])
@if (\All1\LuModels\Support\Ui::flux())
    <flux:button :href="$href" :type="$href ? null : $type" :variant="$variant === 'default' ? 'outline' : $variant" :size="$size" :icon="$icon" {{ $attributes }}>{{ $slot }}</flux:button>
@else
    @php
        $look = match ($variant) {
            'primary' => 'bg-zinc-800 text-white hover:bg-zinc-700 border border-transparent',
            'danger' => 'bg-red-600 text-white hover:bg-red-500 border border-transparent',
            'ghost' => 'text-zinc-700 hover:bg-zinc-100 border border-transparent',
            default => 'bg-white text-zinc-800 border border-zinc-300 hover:bg-zinc-50',
        };
        $pad = $size === 'sm' ? 'px-2.5 py-1.5 text-xs' : 'px-3 py-2 text-sm';
        $classes = "inline-flex items-center gap-1 rounded-md font-medium shadow-sm {$look} {$pad}";
    @endphp
    @if ($href)
        <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
    @else
        <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
    @endif
@endif
