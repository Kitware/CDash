@extends('cdash', [
    'vue' => true
])

@section('main_content')
    <edit-project v-bind:projectid="{{ $project->Id ?? 0 }}"></edit-project>
@endsection
