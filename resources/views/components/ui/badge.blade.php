@props(['color' => 'zinc'])
@if (\All1\LuModels\Support\Ui::flux())
    <flux:badge :color="$color" size="sm" {{ $attributes }}>{{ $slot }}</flux:badge>
@else
    <span {{ $attributes->class(['inline-flex items-center rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700']) }}>{{ $slot }}</span>
@endif
