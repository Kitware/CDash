<?php

use function DI\object;

return [
    Site::class => object()->scope(DI\Scope::PROTOTYPE),
    Build::class => object()->scope(DI\Scope::PROTOTYPE),
    SiteInformation::class => object()->scope(DI\Scope::PROTOTYPE),
    BuildInformation::class => object()->scope(DI\Scope::PROTOTYPE),
    Test::class => object()->scope(DI\Scope::PROTOTYPE),
    Label::class => object()->scope(DI\Scope::PROTOTYPE),
    BuildTest::class => object()->scope(DI\Scope::PROTOTYPE),
    TestMeasurement::class => object()->scope(DI\Scope::PROTOTYPE),
    Image::class => object()->scope(DI\Scope::PROTOTYPE),
    Feed::class => object()->scope(DI\Scope::PROTOTYPE),
];
