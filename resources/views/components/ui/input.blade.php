@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'placeholder' => null, 'description' => null, 'required' => false, 'maxlength' => null])
@if (\All1\LuModels\Support\Ui::flux())
    <flux:input :name="$name" :label="$label" :type="$type" :value="$value" :placeholder="$placeholder" :description="$description ?: null" :required="$required" :maxlength="$maxlength" {{ $attributes }} />
@else
    <div class="lu-field space-y-1">
        @if ($label)
            <label for="lu-{{ $name }}" class="block text-sm font-medium text-zinc-700">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
        @endif
        <input id="lu-{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($value !== null) value="{{ $value }}" @endif @if ($placeholder) placeholder="{{ $placeholder }}" @endif @if ($required) required @endif @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            {{ $attributes->class(['block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:ring-zinc-500']) }} />
        @if ($description)
            <p class="text-xs text-zinc-500">{{ $description }}</p>
        @endif
        @if (isset($errors) && $errors->has($name))
            <p class="text-xs text-red-600">{{ $errors->first($name) }}</p>
        @endif
    </div>
@endif
