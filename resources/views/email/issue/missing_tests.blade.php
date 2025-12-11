<?php
$buildid = $items->first()->buildid;
$url = url("/builds/$buildid/tests");
?>
@foreach($items as $missing_test)
{{ $missing_test->testname }} ({!! $url !!})
@endforeach
