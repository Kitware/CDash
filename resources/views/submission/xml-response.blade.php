<cdash version="{{ config('cdash.version') }}">
@foreach($statusarray as $key => $value)
    <{{ $key }}>{{ $value }}</{{ $key }}>
@endforeach
</cdash>
