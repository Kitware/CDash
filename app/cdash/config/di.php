<?php

use CDash\Middleware\Queue\DriverFactory;

return [
    'submission_queue_name' => function () {
        $config = \CDash\Config::getInstance()->load('queue');
        return isset($config['ctest_submission_queue']) ? $config['ctest_submission_queue'] : null;
    },
    'CDash\Controller\Auth\Session' => \DI\create()
        ->constructor(\DI\get('CDash\System'), \CDash\Config::getInstance()),
    'Bernard\Driver' => \DI\factory(function () {
        return DriverFactory::create();
    }),
    'CDash\Middleware\Queue' => function (Bernard\Driver $driver) {
        return new CDash\Middleware\Queue($driver);
    },
    'CDash\Middleware\Queue\SubmissionService' => function ($di) {
        return new \CDash\Middleware\Queue\SubmissionService($di->get('submission_queue_name'));
    },
];
