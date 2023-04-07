@extends('cdash', [
    'vue' => true
])

@section('main_content')
    <edit-project v-bind:projectid="{{ $projectid }}"></edit-project>
@endsection
