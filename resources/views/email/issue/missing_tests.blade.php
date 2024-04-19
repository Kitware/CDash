<?php
$buildid = $items->first()->buildid;
$url = url('/viewTest.php') . "?buildid={$buildid}";
?>
@foreach($items as $missing_test)
{{ $missing_test->testname }} ({!! $url !!})
@endforeach
