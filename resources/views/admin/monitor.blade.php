@php
    $using_db_queue = config('queue.default') === 'database';
@endphp

@extends('cdash', [
    'vue' => true,
    'title' => 'Monitor Submission Processing'
])

@section('main_content')
    @if ($using_db_queue)
        <monitor />
    @else
        <p>
            System Monitor only available when QUEUE_CONNECTION=database
        </p>
    @endif
@endsection
