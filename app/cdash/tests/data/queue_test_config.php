<?php
use CDash\Middleware\Queue\DriverFactory as Driver;

$queue_config = [
    'drivers' => [
        Driver::FLAT_FILE => [
            'enabled' => true,
            'baseDirectory' => base_path() . '/test_queue_dir',
        ],
    ],
];
return $queue_config;
