<?php

use DI\Factory\RequestedEntry;

$modelFactory = function (RequestedEntry $entry) {
    $model = $entry->getName();
    return new $model();
};

return [
    Site::class => $modelFactory,
    SiteInformation::class => $modelFactory,
    BuildInformation::class => $modelFactory,
    Test::class => $modelFactory,
    Label::class => $modelFactory,
    BuildTest::class => $modelFactory,
    TestMeasurement::class => $modelFactory,
];
