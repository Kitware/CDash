@extends('cdash', [
    'vue' => true,
    'title' => 'Projects',
])

@section('main_content')
    <all-projects :show_all="@json($show_all)"></all-projects>
@endsection
