@props(['name', 'label' => null, 'checked' => false, 'description' => null])
@if (\All1\LuModels\Support\Ui::flux())
    <flux:checkbox :name="$name" :label="$label" :description="$description ?: null" :checked="(bool) $checked" value="1" {{ $attributes }} />
@else
    <div class="lu-field flex items-start gap-2">
        <input type="hidden" name="{{ $name }}" value="0" />
        <input id="lu-{{ $name }}" name="{{ $name }}" type="checkbox" value="1" @checked($checked) {{ $attributes->class(['mt-1 h-4 w-4 rounded border-zinc-300 text-zinc-800 focus:ring-zinc-500']) }} />
        <div>
            @if ($label)
                <label for="lu-{{ $name }}" class="text-sm font-medium text-zinc-700">{{ $label }}</label>
            @endif
            @if ($description)
                <p class="text-xs text-zinc-500">{{ $description }}</p>
            @endif
            @if (isset($errors) && $errors->has($name))
                <p class="text-xs text-red-600">{{ $errors->first($name) }}</p>
            @endif
        </div>
    </div>
@endif
