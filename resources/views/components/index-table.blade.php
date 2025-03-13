<flux:table :paginate="$has_page & $type=='spa' ? $data : null">
    <flux:table.columns>
        <flux:table.column>{{ __(config('lu::models.lang_prefix') . 'ID') }}</flux:table.column>
        @foreach ($db_columns as $col)
            @if (isset($model->hidden_columns) && in_array($col, $model->hidden_columns))
                @continue
            @endif
            <flux:table.column>
                {{ __(config('lu::models.lang_prefix') . \Str::headline(str_replace('.', ' ', $col))) }}
            </flux:table.column>
        @endforeach
    </flux:table.columns>

    <flux:table.row>
        @foreach ($data as $model)
            <?php if(is_array($model)) $model = (object) $model; ?>
            <flux:table.row>
                <flux:table.cell>
                    {{ $model->id ?? ''}}
                </flux:table.cell>

                {{-- @foreach ($model->index_columns as $c) --}}
                @foreach ($db_columns as $c)
                    @if (isset($model->hidden_columns) && in_array($c, $model->hidden_columns))
                        @continue
                    @endif
                    @if (strpos($c, '.') !== false)
                        @php
                            $depth = Str::substrCount($c, '.');
                            $parent = $model;
                            for ($i = 0; $i < $depth; $i++) {
                                $tmp = explode('.', $c); //[0]=>pimp,1=>id
                                $relationship_method = $tmp[$i]; //pimp
                                $relationship = $parent->$relationship_method;
                                $field = $tmp[$i + 1]; //id
                            }

                        @endphp
                        <flux:table.cell>
                            @if (is_iterable($relationship))
                                @foreach ($relationship as $r)
                                    @if (method_exists($r, 'displayText'))
                                        <flux:badge>{{ $r->displayText($field) }} </flux:badge>
                                    @else
                                        <flux:badge>{{ $r->$field }}</flux:badge>
                                    @endif
                                @endforeach
                            @elseif($relationship !== null)
                                @if (method_exists($relationship, 'getAttributes'))
                                    <flux:badge>{{ $relationship->$field }}</flux:badge>
                                @else
                                    @foreach ($relationship as $r)
                                        <flux:badge>{{ json_encode($r) }}</flux:badge>
                                    @endforeach
                                @endif
                            @endif
                        </flux:table.cell>
                    @else
                        <flux:table.cell>
                            @if (method_exists($c, 'displayText'))
                            {{ $model->displayText($c) }}
                        @else
                            <flux:badge>{{ $model->$c??'' }}</flux:badge>
                        @endif
                        </flux:table.cell>
                    @endif
                @endforeach



                <flux:table.cell>
                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom">
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item
                                href="{{ route('lu::web.resources', [$route_prefix, 'edit', $model->id??NULL]) }}"
                                icon="pencil">Edit</flux:menu.item>

                            <flux:menu.separator />



                            <flux:menu.separator />
                            <form method="POST"
                                action="{{ route('lu::api.resources', [$route_prefix, 'destroy', $model->id??NULL]) }}">
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