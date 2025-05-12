@extends('cdash', [
    'vue' => true,
])

@section('main_content')
    <{{ $componentName }}
    @foreach($props as $prop => $value)
        :{{ $prop }}="@js($value)"
    @endforeach
    ></{{ $componentName }}>
@endsection
