@foreach($items as $failing_tests)
{{ $failing_tests->Name }} | {{ $failing_tests->Details }} | ({!! $failing_tests->GetUrlForSelf() !!})
@endforeach
