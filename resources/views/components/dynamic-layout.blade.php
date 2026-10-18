@extends($layout)

@section('header')
    {{ $header ?? '' }}
@endsection

@section('content')
    {{ $content ?? $slot }}
@endsection
