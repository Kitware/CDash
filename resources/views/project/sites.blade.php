@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <project-sites-page :project-id="{{ $project->Id }}"></project-sites-page>
@endsection
