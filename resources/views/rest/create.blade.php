<x-lu_models::dynamic-layout :layout="config('lu::models.layout','components.layouts.app')">


    
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __( config("lu::models.lang_prefix") . $action) . ' ' . Str::singular(__( config("lu::models.lang_prefix") . $route_prefix)) }}
        </h2>
    
<div class="text-red-500 text-xs">
    This will submit to the API route so you can test. Will try to do it inside the iframe below.<br>
    You can override the default creation method by creatinog the Laravel Actions package custom class for Model in:
    '\App\Models\Actions\LuModels\Create'.ucfirst(class_basename($this)); , so for User it would be app/Actions/LuModels/CreateUser
    where LuModels can be changed with models $action_folder.
</div>

<iframe id="resultFrame" name="resultFrame" width="100%" height="400px"></iframe>

    @if (isset($columns))

        <form action="{{ route('lu::api.resources', [$route_prefix, 'store']) }}" method="POST" target="resultFrame">
            @csrf
            @method('PUT')

            @foreach ($columns as $c)
                
                @php
                    

                    $label = str_replace('_', ' ', ucfirst($c['Field']));

                @endphp
                @if (!in_array($c['Field'], ['id', 'created_at', 'updated_at', 'user_id', 'slug']))
                    <div class="p-2">
                        @if ($c['required'])
                            <span class="text-red-500 absolute -mt-1 -ml-2">*</span>
                        @endif
                        @if ($c['input_type'] == 'textarea')
                            <flux:textarea :maxlenght="$c['max_length']" :name="$c['Field']"  
                                :type="$c['Type']" :label="$label" :description="$c['description']"
                                :required="$c['required']" />
                        @elseif($c['input_type'] == 'date')
                            <flux:date-picker :maxlenght="$c['max_length']" :name="$c['Field']"
                                 :type="$c['Type']" :label="$label"
                                :description="$c['description']" :required="$c['required']" />
                        @else
                            <flux:input :placeholder="'e.g. '.$c['sample']" :maxlenght="$c['max_length']"
                                :name="$c['Field']"   :type="$c['Type']"
                                :label="$label" :description="$c['description']" :required="$c['required']" />
                        @endif
                    </div>
                @endif
            @endforeach

            <div class="p-2">
                <flux:button type="submit" class="bg-green-500 text-white">Save</flux:button>
            </div>

        </form>
    @else
        NO DATA FOUND!

    @endif

</x-lu_models::dynamic-layout>