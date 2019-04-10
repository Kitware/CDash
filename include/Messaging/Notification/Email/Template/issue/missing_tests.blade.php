<?php
use CDash\Config;
$config = Config::getInstance();
$buildid = $items->current()->GetBuildTest()->BuildId;
$url = "{$config->getBaseUrl()}/viewTest.php?buildid={$buildid}";
?>
@foreach($items as $missing_tests)
{{ $missing_tests->Name }} ({!! $url !!})
@endforeach
