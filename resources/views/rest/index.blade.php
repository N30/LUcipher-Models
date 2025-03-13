<?php 
    $layout = config('lu::models.layout');//config('models.layout'); 
    //with get params
    $this_page_url = url()->current()."?".http_build_query(request()->all());

?>
 
<x-lu_models::dynamic-layout :layout="$layout">
   
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300..700&display=swap" rel="stylesheet">

@if(!$meta['extends_lu_model'])
    <span class="info">For your info this model does not extend the LuModel</span>
@endif
 

   
        <flux:button href="{{ route('lu::rest.resources', [$route_prefix, 'create']) }}" variant="primary" size="sm" class="float-end">
            {{__('Create New')}} {{__( config('lu::models.lang_prefix').$route_prefix )}}
        </flux:button>

        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ \Str::headline(__( config('lu::models.lang_prefix') . $action)) . ' ' . __( config('lu::models.lang_prefix') . \Str::plural($route_prefix)) }}
        </h2>
    

    @php $hasPage = $data->hasPages(); @endphp
    <!--make pagination not load on hydrating !-->
    
    <pre style='font-size:10px;font-family: "Fira Code", monospace;font-optical-sizing: auto;'>
    //everything minimum to be displayed in the index page
    public $index_columns = ['name', 'pimp.name',  'description' , 'bankAccount.name', 'created_at', 'pimp.user.name'];<br>
    //if not defined all relationships will be allowed as LuModel will get all 2 depth relationships<br>
    public $index_relationships = ['pimp','pimp.user','bankAccount'];<br>
    </pre>
    {{ str_replace('rest', config("lu::models.api_prefix").'api',$this_page_url) }}
    <iframe id="pagination" src="{{ str_replace('rest', config("lu::models.api_prefix").'api',$this_page_url) }}" class="w-full h-100" style="min-height:150px;"></iframe>


    <div id="noscript_pagination">
        {!! $data->onEachSide(2)->links() !!}
    </div>

    {{--  $data->links() --}}
 
    <hr>

    @include('lu_models::web.index')

</x-lu_models::dynamic-layout>