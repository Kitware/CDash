@extends('cdash', [
    'vue' => true,
    'title' => 'SubProject Dependencies Graph',
])

@section('main_content')
    <subproject-dependencies></subproject-dependencies>
@endsection
