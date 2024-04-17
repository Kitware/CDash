<?php
$buildid = $items->first()->buildid;
$url = url('/viewTest.php') . "?buildid={$buildid}";
?>
@foreach($items as $missing_tests)
{{ $missing_tests->test->name }} ({!! $url !!})
@endforeach
