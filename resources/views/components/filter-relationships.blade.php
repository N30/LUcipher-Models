 
 <flux:select
    x-data="{ expanded: false }"
     x-on:change="needs_submit=1" 
     x-bind:style="expanded ? 'height: 150px;position:fixed; width:300px;z-index:3;' : 'height: 40px;'" 
     x-on:mouseover="expanded = true" 
     x-on:mouseleave="expanded = false" 
     name="with[]" 
     :variant="$type=='spa' ? 'listbox' : 'default'" multiple
     placeholder="Choose Relationsips...">
     
    @if(!is_array($model) && null !== ($model->getModelRelationships()))
     @foreach ($model->getModelRelationships() as $relationship)
         @if (isset($model->default_loaded_relationships) && in_array($relationship, $model->default_loaded_relationships??[]))
             <flux:select.option selected disabled>
                 <flux:icon.check class="absolute" />
                 {{ Str::headline($relationship) }}
             </flux:select.option>
         @else
             <flux:select.option  value="{{ $relationship }}" :selected="in_array($relationship, $filters['with']??[])">
                 {{ Str::headline($relationship) }}
             </flux:select.option>
         @endif
     @endforeach
    @endif
 </flux:select>
 