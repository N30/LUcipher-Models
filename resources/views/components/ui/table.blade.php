{{-- $columns: list of ['label' => string, 'href' => string|null (sort link), 'direction' => 'asc'|'desc'|null] --}}
@props(['columns' => []])
@if (\All1\LuModels\Support\Ui::flux())
    <flux:table {{ $attributes }}>
        <flux:table.columns>
            @foreach ($columns as $column)
                <flux:table.column>
                    @if ($column['href'] ?? null)
                        <a href="{{ $column['href'] }}" class="inline-flex items-center gap-1">{{ $column['label'] }}@if ($column['direction'] ?? null)<span aria-hidden="true">{{ $column['direction'] === 'asc' ? '▲' : '▼' }}</span>@endif</a>
                    @else
                        {{ $column['label'] }}
                    @endif
                </flux:table.column>
            @endforeach
        </flux:table.columns>
        <flux:table.rows>{{ $slot }}</flux:table.rows>
    </flux:table>
@else
    <div class="overflow-x-auto">
        <table {{ $attributes->class(['min-w-full divide-y divide-zinc-200 text-sm']) }}>
            <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col" class="px-3 py-2">
                            @if ($column['href'] ?? null)
                                <a href="{{ $column['href'] }}" class="inline-flex items-center gap-1 hover:text-zinc-900">{{ $column['label'] }}@if ($column['direction'] ?? null)<span aria-hidden="true">{{ $column['direction'] === 'asc' ? '▲' : '▼' }}</span>@endif</a>
                            @else
                                {{ $column['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white">{{ $slot }}</tbody>
        </table>
    </div>
@endif
