@extends('cdash', [
    'title' => '404 Not Found'
])

@section('main_content')
    @if(strlen($exception->getMessage()) > 0)
        {{ $exception->getMessage() }}
    @else
        The requested page does not exist.
    @endif
@endsection
