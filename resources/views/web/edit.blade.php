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
            <form wire:submit='beforeUpdate' action="{{ route('lu::web.resources', [$route_prefix, 'update', $model->id]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach ($columns as $c)
                        @php
                            $label = str_replace('_', ' ', ucfirst($c['Field']));
                        @endphp
                        @if (!in_array($c['Field'], ['id', 'created_at', 'updated_at', 'slug']))
                            @if (auth()->user()->isAdmin() || !in_array($c['Field'], ['user_id']))
                                <div class="p-4 border rounded-lg bg-gray-50">
                                    <label class="block text-zinc-700 font-semibold">{{ $label }}</label>
                                    @if (strpos($c['Field'], 'photo') !== false)
                                        <img src="{{ $model->{$c['Field']} }}" class="h-32 w-32 rounded-xl border-zinc-700 border mb-2" />
                                        <flux:input :name="$c['Field']" :type="'file'" :label="$label" wire:model="model_attributes.{{ $c['Field'] }}"   />
                                    @elseif($c['input_type'] == 'textarea')
                                        <flux:textarea value="{{ old($c['Field']) ?? $model->{$c['Field']} }}"
                                            :name="$c['Field']" :type="$c['Type']" wire:model="model_attributes.{{ $c['Field'] }}"  
                                            :label="$label" :description="$c['description']" />
                                    @elseif($c['input_type'] == 'date')
                                        <flux:date-picker value="{{ old($c['Field']) ?? $model->{$c['Field']} }}"
                                            :name="$c['Field']" :type="$c['Type']" wire:model="model_attributes.{{ $c['Field'] }}"  
                                            :label="$label" :description="$c['description']" />
                                    @else
                                        <flux:input value="{{ old($c['Field']) ?? $model->{$c['Field']} }}" wire:model="model_attributes.{{ $c['Field'] }}"  
                                            :placeholder="'e.g. ' . $c['sample']" :name="$c['Field']"
                                            :type="$c['Type']" :label="$label" :description="$c['description']" />
                                    @endif
                                </div>
                            @endif
                        @endif
                    @endforeach
                </div>

                <div class="mt-6">
                    <h2 class="text-xl font-bold mb-4">Relationships</h2>
                    @foreach ($model->getRelations() as $relation => $items)
                        <div class="p-4 border rounded-lg bg-gray-50">
                            <h3 class="text-lg font-semibold text-zinc-700">{{ ucfirst($relation) }}</h3>
                            <ul class="list-disc list-inside">
                               @if($items) @forelse ($items as $item)
                                    <li class="text-zinc-600">{{ $item->name ?? 'Unnamed Item' }}</li>
                                @empty
                                    <li class="text-zinc-400">No related data found.</li>
                                @endforelse
                                @endif
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 text-right">
                    <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

</x-lu_models::dynamic-layout>