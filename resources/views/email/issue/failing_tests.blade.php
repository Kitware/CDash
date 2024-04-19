@foreach($items as $failing_test)
{{ $failing_test->testname }} | {{ $failing_test->details }} | ({!! $failing_test->GetUrlForSelf() !!})
@endforeach
