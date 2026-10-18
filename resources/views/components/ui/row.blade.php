@if (\All1\LuModels\Support\Ui::flux())
    <flux:table.row {{ $attributes }}>{{ $slot }}</flux:table.row>
@else
    <tr {{ $attributes }}>{{ $slot }}</tr>
@endif
