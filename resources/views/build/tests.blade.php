@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <build-tests-page buildid="{{ $build->Id }}"></build-tests-page>
@endsection
