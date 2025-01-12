@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <sites-id-page :site-id="{{ $site->id }}"></sites-id-page>
@endsection
