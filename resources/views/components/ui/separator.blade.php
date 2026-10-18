@if (\All1\LuModels\Support\Ui::flux())
    <flux:separator {{ $attributes }} />
@else
    <hr {{ $attributes->class(['my-4 border-zinc-200']) }} />
@endif
