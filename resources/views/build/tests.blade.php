@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <build-tests-page :build-id="{{ $build->Id }}" :initial-filters="@js($filters)"></build-tests-page>
@endsection
