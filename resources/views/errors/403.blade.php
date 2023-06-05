@extends('cdash', [
    'title' => '403 Forbidden'
])

@section('main_content')
    @if(strlen($exception->getMessage()) > 0)
        {{ $exception->getMessage() }}
    @else
        You do not have access to the requested page.
    @endif
@endsection
