{{-- The /spa shell: the host layout, the toasts, and the one Livewire component that owns this page. --}}
{{-- ($component is Blade's own variable for the current Blade component inside <x-...> tags, hence the name.) --}}
<x-lu_models::dynamic-layout :layout="$layout">
    <x-lu_models::ui.toasts />
    @livewire($livewireComponent, $params)
</x-lu_models::dynamic-layout>
