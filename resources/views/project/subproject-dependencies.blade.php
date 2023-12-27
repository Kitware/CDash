@extends('cdash', [
    'vue' => true,
    'title' => 'SubProject Dependencies Graph',
])

@section('main_content')
    <sub-project-dependencies
        :project-name="'{{ $project->Name }}'"
        :date="'{{ $date }}'"
    />
@endsection
