<?php if (!isset($layout)) {
    $layout = config('lu::models.layout'); //config('models.layout');
} else {
    $layout = 'lu_models::empty-layout';
}

?>

<x-lu_models::dynamic-layout :layout="$layout">

   
        <flux:button href="{{ route('lu::web.resources', [$route_prefix, 'create']) }}" variant="primary" size="sm"
            class="float-end">
            {{ __('Create New') }} {{ __( config("lu::models.lang_prefix") . $route_prefix) }}
        </flux:button>

        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ ucfirst(__( config("lu::models.lang_prefix"). $action)) . ' ' . __( config("lu::models.lang_prefix") . Str::plural($route_prefix)) }}
        </h2>
    
        <flux:select variant="listbox" multiple name="relationship" placeholder="Choose Relationsips...">
            @foreach($data[0]->getModelRelationships() as $relationship)
                <flux:select.option>{{ $relationship }}</flux:select.option>
            @endforeach
        </flux:select>

        
        @php  
        try { $hasPage = $data->hasPages();  }
        catch (\Exception $e) { $hasPage = 0; }
    @endphp
    <!--make pagination not load on hydrating !-->
    <flux:table :paginate="$hasPage ? $data : null">

        <flux:table.columns>
            <flux:table.column>{{__( config("lu::models.lang_prefix").'ID')}}</flux:table.column>
            @foreach ($data[0]->index_columns as $c)
                
                    <flux:table.column>{{ __( config("lu::models.lang_prefix"). \Str::headline( str_replace('.',' ',$c) ) ) }}</flux:table.column>
           
            @endforeach
        </flux:table.columns>

        <flux:table.row>
            @foreach ($data as $model)
                <flux:table.row>
                    <flux:table.cell>
                        {{ $model->id }}
                    </flux:table.cell>
                    
                   
                @foreach ($model->index_columns as $c)
                    @if(strpos($c,'.') !== false)
                        @php
                            $depth = Str::substrCount($c,'.');
                            $parent = $model;
                            for($i=0;$i<$depth;$i++){
                                $tmp = explode('.',$c); //[0]=>pimp,1=>id
                                $relationship_method = $tmp[$i];//pimp
                                $relationship = $parent->$relationship_method;
                                $field = $tmp[$i+1]; //id 
                            }
                            
                        @endphp
                        <flux:table.cell>
                            @if( is_iterable($relationship) )
                                @foreach($relationship as $r)
                                    @if( method_exists($r,'displayText') )
                                    <flux:badge >{{ $r->displayText( $field) }} </flux:badge>
                                    @else
                                    <flux:badge >{{ $r->$field }}</flux:badge>
                                    @endif 
                                @endforeach
                            @else 
                                @if( method_exists($relationship,'displayText') )
                                {{ $relationship->displayText( $field) }} 
                                @else
                                    {{ $relationship->$field }}
                                @endif 
                            @endif
                        </flux:table.cell>
                    @else 
                    <flux:table.cell>
                        {{ $model->displayText( $c) }} 
                    </flux:table.cell>
                    @endif
                @endforeach



                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom">
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item
                                    href="{{ route('lu::web.resources', [$route_prefix, 'edit', $model->id]) }}"
                                    icon="pencil">Edit</flux:menu.item>

                                <flux:menu.separator />

                                <flux:menu.submenu heading="Sort by">
                                    <flux:menu.radio.group>
                                        <flux:menu.radio checked>Name</flux:menu.radio>
                                        <flux:menu.radio>Date</flux:menu.radio>
                                        <flux:menu.radio>Popularity</flux:menu.radio>
                                    </flux:menu.radio.group>
                                </flux:menu.submenu>

                                <flux:menu.submenu heading="Filter">
                                    <flux:menu.checkbox checked>Draft</flux:menu.checkbox>
                                    <flux:menu.checkbox checked>Published</flux:menu.checkbox>
                                    <flux:menu.checkbox>Archived</flux:menu.checkbox>
                                </flux:menu.submenu>

                                <flux:menu.separator />
                                <form method="POST"
                                    action="{{ route('lu::web.resources', [$route_prefix, 'destroy', $model->id]) }}">
                                    @csrf
                                    @method('DELETE')


                                    <flux:menu.item variant="danger" icon="trash" type="submit">
                                        Delete
                                    </flux:menu.item>

                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.row>
    </flux:table>

    <div id="noscript_pagination">
        {!! $data->onEachSide(2)->links() !!}
    </div>

    {{--  $data->links() --}}

</x-lu_models::dynamic-layout>
