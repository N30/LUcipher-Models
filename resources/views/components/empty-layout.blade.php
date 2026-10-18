{{-- A fragment inside a page: the slots as they are, no @extends (see View\Components\DynamicLayout). --}}
{{ $header ?? '' }}
{{ $content ?? $slot }}
