<?php

use CDash\Middleware\Queue\DriverFactory;

return [
    'CDash\Controller\Auth\Session' => \DI\object()
        ->constructor(\DI\get('CDash\System'), \CDash\Config::getInstance()),
    'Bernard\Driver' => \DI\factory(function () {
        return DriverFactory::create();
    }),
    'CDash\Middleware\Queue' => function (Bernard\Driver $driver) {
        return new CDash\Middleware\Queue($driver);
    }
];
