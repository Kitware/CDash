@extends('cdash', [
    'title' => '401 Unauthorized'
])

@section('main_content')
    @if(strlen($exception->getMessage()) > 0)
        {{ $exception->getMessage() }}
    @elseif(Auth::check())
        You do not have access to the requested page.
    @else
        You must be logged in to view the requested page.
    @endif
@endsection
