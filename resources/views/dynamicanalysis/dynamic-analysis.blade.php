@extends('cdash', [
    'vue' => true,
    'title' => 'Dynamic Analysis'
])

@section('main_content')
    <view-dynamic-analysis buildid="{{ $build->Id }}"></view-dynamic-analysis>
@endsection
