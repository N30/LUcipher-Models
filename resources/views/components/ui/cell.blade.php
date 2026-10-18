@if (\All1\LuModels\Support\Ui::flux())
    <flux:table.cell {{ $attributes }}>{{ $slot }}</flux:table.cell>
@else
    <td {{ $attributes->class(['px-3 py-2 align-top text-zinc-700']) }}>{{ $slot }}</td>
@endif
