<?php

// TODO: when an application bootstrap exists, move this there
require __DIR__ . '/../vendor/autoload.php';

use CDash\Middleware\Queue\DriverFactory;

return [
    'submission_queue_name' => function () {
        $config = \CDash\Config::getInstance()->load('queue');
        return isset($config['ctest_submission_queue']) ? $config['ctest_submission_queue'] : null;
    },
    'CDash\Controller\Auth\Session' => \DI\object()
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
    'CDash\Lib\SubmissionParserInterface' => DI\factory(function () {
        $config = \CDash\Config::getInstance()->load('parser');
        $parser = null;
        // TODO: remove global $_REQUEST checking in favor of Request object asap
        if (isset($_REQUEST['type']) && isset($_REQUEST['buildid'])) {
            $type = $_REQUEST['type'];
            $buildId = $_REQUEST['buildid'];
            if (isset($config[$type])) {
                $parserClass = $config[$type];
                $parser = new $parserClass($buildId);
            }
        } else {
            // TODO: When the time comes, a factory that creates a parser based on
            //       the submission input will set the value of $parser here.
        }

        // TODO: throw exception here if parser still null
        return $parser;
    }),
];
