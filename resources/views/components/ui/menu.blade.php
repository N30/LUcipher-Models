@props(['label' => 'Actions'])
@if (\All1\LuModels\Support\Ui::flux())
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="$label" />
        <flux:menu>{{ $slot }}</flux:menu>
    </flux:dropdown>
@else
    <details class="relative inline-block text-left">
        <summary class="cursor-pointer list-none rounded-md px-2 py-1 text-sm text-zinc-600 hover:bg-zinc-100" aria-label="{{ $label }}">&#8943;</summary>
        <div class="absolute right-0 z-10 mt-1 min-w-40 rounded-md border border-zinc-200 bg-white p-1 shadow-lg">{{ $slot }}</div>
    </details>
@endif
