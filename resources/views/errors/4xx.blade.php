@extends('cdash', [
    'title' => $exception->getStatusCode()
])

@section('main_content')
    {{ $exception->getMessage() }}
@endsection
