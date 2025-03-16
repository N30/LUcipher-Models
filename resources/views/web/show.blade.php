<?php if (!isset($layout)) {
    $layout = config('lu::models.layout'); //config('models.layout');
} else {
    $layout = 'lu_models::empty-layout';
}
?>
<x-lu_models::dynamic-layout :layout="$layout">


    <x-slot name="header">

        {{ __(ucfirst($action)) . ' ' . __(ucfirst(Str::singular($route_prefix))) . ' #' . $model->id }}

    </x-slot>

    <x-slot name="content">
        <div class="flex flex-col space-y-4 p-6 bg-white rounded-xl shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach ($columns as $c)
                    @php
                        $label = str_replace('_', ' ', ucfirst($c['Field']));
                    @endphp
                    @if (!in_array($c['Field'], ['id', 'created_at', 'updated_at', 'user_id', 'slug']))
                        <div class="p-4 border rounded-lg bg-gray-50">
                            <h3 class="text-lg font-semibold text-zinc-700">{{ $label }}</h3>
                            <div class="mt-2">
                                @if (strpos($c['Field'], 'photo') !== false)
                                    <img src="{{ $model->{$c['Field']} }}" 
                                         class="h-32 w-32 rounded-xl border-zinc-700 border" />
                                @elseif($c['input_type'] == 'textarea')
                                    <div class="p-3 bg-gray-100 rounded-md border">{{ $model->{$c['Field']} }}</div>
                                @elseif($c['input_type'] == 'date')
                                    <div class="text-zinc-500">{{ \Carbon\Carbon::parse($model->{$c['Field']})->format('M d, Y') }}</div>
                                @elseif(is_numeric($model->{$c['Field']}))
                                    <div class="font-mono text-green-600">{{ number_format($model->{$c['Field']}, 2) }}</div>
                                @else
                                    <div class="text-zinc-600">{{ $model->{$c['Field']} }}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-6">
                <h2 class="text-xl font-bold mb-4">Relationships</h2>
                @foreach ($model->getRelations() as $relation => $items)
                    <div class="p-4 border rounded-lg bg-gray-50">
                        <h3 class="text-lg font-semibold text-zinc-700">{{ ucfirst($relation) }}</h3>
                        <ul class="list-disc list-inside">
                            @if($items)
                                @forelse ($items as $item)
                                    <li class="text-zinc-600">{{ $item->name ?? 'Unnamed Item' }}</li>
                                @empty
                                    <li class="text-zinc-400">No related data found.</li>
                                @endforelse
                            @else
                                <li class="text-zinc-400">No related data found.</li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 text-right">
                <a href="{{ route('lu::web.resources', [$route_prefix, 'edit', $model->id]) }}" 
                   class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600">
                   Edit
                </a>
            </div>
        </div>

</x-lu_models::dynamic-layout>