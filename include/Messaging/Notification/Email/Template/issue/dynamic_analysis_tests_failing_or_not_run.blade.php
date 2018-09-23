@foreach($items as $dynamic_analysis_tests_failing_or_not_run)
{{ $dynamic_analysis_tests_failing_or_not_run->Name }} ({!! $dynamic_analysis_tests_failing_or_not_run->GetUrlForSelf() !!})
@endforeach
