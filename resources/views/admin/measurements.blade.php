@extends('cdash', [
    'vue' => true
])

@section('main_content')
    <manage-measurements v-bind:projectid="{{ $project->Id ?? 0 }}"></manage-measurements>
@endsection
