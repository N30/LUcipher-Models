@props(['name', 'label' => null, 'options' => [], 'selected' => [], 'multiple' => false, 'placeholder' => null, 'description' => null])
@php $selected = array_map('strval', (array) $selected); @endphp
@if (\All1\LuModels\Support\Ui::flux() && ! $multiple)
    <flux:select :name="$name" :label="$label" :placeholder="$placeholder" :description="$description ?: null" {{ $attributes }}>
        @foreach ($options as $value => $text)
            <flux:select.option :value="$value" :selected="in_array((string) $value, $selected, true)">{{ $text }}</flux:select.option>
        @endforeach
    </flux:select>
@else
    <div class="lu-field space-y-1">
        @if ($label)
            <label for="lu-{{ $name }}" class="block text-sm font-medium text-zinc-700">{{ $label }}</label>
        @endif
        <select id="lu-{{ $name }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}" @if ($multiple) multiple @endif {{ $attributes->class(['block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:ring-zinc-500']) }}>
            @if ($placeholder && ! $multiple)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected(in_array((string) $value, $selected, true))>{{ $text }}</option>
            @endforeach
        </select>
        @if ($description)
            <p class="text-xs text-zinc-500">{{ $description }}</p>
        @endif
    </div>
@endif
