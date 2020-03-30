@foreach($items as $failing_tests)
{{ $failing_tests->test->name }} | {{ $failing_tests->details }} | ({!! $failing_tests->GetUrlForSelf() !!})
@endforeach
