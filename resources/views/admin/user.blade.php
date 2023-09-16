@extends('cdash', [
    'vue' => true,
    'title' => 'My Profile'
])

@section('main_content')
    @verbatim
        <user-homepage></user-homepage>
    @endverbatim
@endsection
