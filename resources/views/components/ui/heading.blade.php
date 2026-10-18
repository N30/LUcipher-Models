@props(['size' => 'xl', 'level' => 1])
@if (\All1\LuModels\Support\Ui::flux())
    <flux:heading :size="$size" :level="$level" {{ $attributes }}>{{ $slot }}</flux:heading>
@else
    <h{{ $level }} {{ $attributes->class([$size === 'lg' ? 'text-lg font-semibold text-zinc-800' : 'text-xl font-semibold text-zinc-800']) }}>{{ $slot }}</h{{ $level }}>
@endif
