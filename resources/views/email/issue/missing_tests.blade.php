<?php
use CDash\Config;

$config = Config::getInstance();
$buildid = $items->first()->buildid;
$url = "{$config->getBaseUrl()}/viewTest.php?buildid={$buildid}";
?>
@foreach($items as $missing_tests)
{{ $missing_tests->test->name }} ({!! $url !!})
@endforeach
