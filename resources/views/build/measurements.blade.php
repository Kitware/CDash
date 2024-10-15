@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <build-measurements-page :build-id="{{ $build->Id }}" :initial-filters="@js($filters)"></build-measurements-page>
@endsection
