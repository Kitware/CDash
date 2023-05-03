@extends('cdash', [
    'vue' => true
])

@section('main_content')
    <manage-measurements v-bind:projectid="{{ $projectid }}"></manage-measurements>
@endsection
