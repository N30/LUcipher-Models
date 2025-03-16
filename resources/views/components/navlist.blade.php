{{--
    CREATE SOME SPACE
--}}
<table>
    <flux:navlist variant="outline">
        <flux:navlist.group heading="{{strtoupper($type)}}"  class="grid">
            @foreach ($iterable as $menu)
                @foreach ($menu['routes'][$type] as $name => $route)
                    {{--@if($route['action'] !== 'GET' || $route['dynamic'])--}}
                    @if($name !== 'index' || $route['dynamic'])
                        @continue
                    @endif
                    @php $route = route('lu::'.$type.'.resources', ['model' => $menu['route_group'], 'action' => $name]); @endphp
                    <flux:navlist.item  :href="$route"  :current="request()->routeIs('lu::'.$type.'.resources') && request()->route('model')==strtolower($menu['name']) /*&& request()->route('action')==$name*/"  wire:navigate>
                        {{-- ucfirst(__(config('lu::models.lang_prefix') . $name)) --}} {{ ucfirst(__(config('lu::models.lang_prefix') . strtolower($menu['name']))) }}
                    </flux:navlist.item>
                @endforeach
            @endforeach
        </flux:navlist.group>
    </flux:navlist>
</table>
