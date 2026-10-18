{{--
    The create/edit form, from Form::fields(): the same contract the API publishes (which fields, which
    are required, how long) with the descriptor's input kind and help text. On /spa the wire: attributes
    bind the same fields to the Livewire form; unhydrated it is a plain HTML POST the web controller handles.
--}}
@php $fields = \All1\LuModels\Support\Form::fields($model, $action, old()); @endphp
<form action="{{ $submit }}" method="POST" class="lu-form space-y-4" wire:submit="save">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ($fields as $f)
            <div @class(['md:col-span-2' => $f['input'] === 'textarea'])>
                @if ($f['input'] === 'textarea')
                    <x-lu_models::ui.textarea :name="$f['name']" :label="$f['label']" :value="$f['value']" :description="$f['help']" :required="$f['required']" :maxlength="$f['maxlength']" wire:model="form.{{ $f['name'] }}" />
                @elseif ($f['type'] === 'checkbox')
                    <x-lu_models::ui.checkbox :name="$f['name']" :label="$f['label']" :checked="(bool) $f['value']" :description="$f['help']" wire:model="form.{{ $f['name'] }}" />
                @else
                    <x-lu_models::ui.input :name="$f['name']" :label="$f['label']" :type="$f['type']" :value="$f['value']" :description="$f['help']" :required="$f['required']" :maxlength="$f['maxlength']" wire:model="form.{{ $f['name'] }}" />
                @endif
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-end gap-2">
        <x-lu_models::ui.button :href="$cancel" variant="ghost">{{ __('Cancel') }}</x-lu_models::ui.button>
        <x-lu_models::ui.button type="submit" variant="primary">{{ $action === 'create' ? __('Save') : __('Save changes') }}</x-lu_models::ui.button>
    </div>
</form>
