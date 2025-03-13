<x-lu_models::dynamic-layout :layout="config('lu::models.layout','components.layouts.app')">


    <x-slot name="header">

        {{ __(ucfirst($action)) . ' ' . __(ucfirst(Str::singular($route_prefix))) . ' #' . $model->id }}

    </x-slot>

    <x-slot name="content">
        <div class="flex flex-col space-y-4">
            <form action="{{ route('lu::web.resources', [$route_prefix, 'update', $model->id]) }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @foreach ($columns as $c)
                 
                    @php
                        //GET ri of the paraentesis and inside it: Int(11)=>int
 
                        $label = str_replace('_', ' ', ucfirst($c['Field']));

                    @endphp
                    @if (!in_array($c['Field'], ['id', 'created_at', 'updated_at', 'user_id', 'slug']))
                        @if (auth()->user()->isAdmin() || !in_array($key, $adminOnly = ['user_id']))
                            <div class="p-2">
                                @if ($c['required'])
                                    <span class="text-red-500 absolute -mt-1 -ml-2">*</span>
                                @endif
                                @if (strpos($c['Field'], 'photo') !== false)
                                    <img src="{{ $model->{$c['Field']} }}"
                                        class="h-20 w-20 rounded-xl border-zinc-700 border" />
                                    <flux:input :name="$c['Field']" :type="'file'" :label="$label"
                                        :description="$c['description']" />
                                @elseif($c['input_type'] == 'textarea')
                                    <flux:textarea value="{{ old($c['Field']) ?? $model->{$c['Field']} }}"
                                        :maxlenght="$c['max_length']" :name="$c['Field']"
                                        :type="$c['Type']" :label="$label" :description="$c['description']"
                                        :required="$c['required']" />
                                @elseif($c['input_type'] == 'date')
                                    <flux:date-picker value="{{ old($c['Field']) ?? $model->{$c['Field']} }}"
                                        :maxlenght="$c['max_length']" :name="$c['Field']"
                                        :type="$c['Type']" :label="$label"
                                        :description="$c['description']" :required="$c['required']" />
                                @else
                                    <flux:input value="{{ old($c['Field']) ?? $model->{$c['Field']} }}"
                                        :placeholder="'e.g. '.$c['sample']" :maxlenght="$c['max_length']"
                                        :name="$c['Field']" :type="$c['Type']"
                                        :label="$label" :description="$c['description']"
                                        :required="$c['required']" />
                                @endif
                            </div>
                        @else
                            {{ $key }}:{{ $value }}
                        @endif
                    @endif
                @endforeach
                <br>
                <flux:button type="submit" class="bg-green-500 text-white">Save</flux:button>
            </form>
        </div>

</x-lu_models::dynamic-layout>