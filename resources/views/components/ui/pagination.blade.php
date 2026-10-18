@props(['paginator'])
@if ($paginator instanceof \Illuminate\Contracts\Pagination\Paginator && $paginator->hasPages())
    @if (\All1\LuModels\Support\Ui::flux())
        <flux:pagination :paginator="$paginator" {{ $attributes }} />
    @else
        <div {{ $attributes->class(['mt-4']) }}>{{ $paginator->onEachSide(1)->links() }}</div>
    @endif
@endif
