<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

/*
 * CDash uses Bernard to abstract different message queue systems. This configuration
 * is used by CDash's QueueFactory to create the appropriate driver for Bernard to
 * use. Currently only one configuration can be enabled and you must ensure that you
 * have installed the appropriate composer libraries for your particular message queue.
 */

use CDash\Middleware\Queue\DriverFactory as Driver;

return [
    Driver::APP_ENGINE => [
        'enabled' => false,
        'queueMap' => [
            'queue-name' => '/url_endpoint',
        ],
    ],
    Driver::DOCTRINE => [
        'enabled' => false,
        'dbname' => '',
        'user' => '',
        'password' => null,
        'host' => 'localhost',
        'driver' => 'pdo_mysql',
    ],
    Driver::FLAT_FILE => [
        'enabled' => false,
        'baseDirectory' => '/path/to/your/queue/dir',
    ],
    // While it is absolutely possible to run IronMQ, it will take a bit of
    // massaging paying particular attention to the fact that Bernard ~0.12
    // IronMQ driver expects version <= 1.5.3 of IronMQ and not the composer
    // installable iron-io/iron_mq beginning at version 2.
    /*
    Driver::IRON_MQ => [
        'enabled' => false,
        'name_properties' => [
            'token' => '',
            'project_id' => '',
        ]
    ],
    */
    Driver::PHP_REDIS => [
        'enabled' => false,
        'host' => 'localhost',
        'prefix' => 'bernard:',
    ],
    Driver::PREDIS => [
        'enabled' => false,
        'prefix' => 'bernard:',
    ],
    Driver::SQS => [
        'enabled' => false,
        'profile' => 'CDASH',
        'region' => 'us-east-1',
        'version' => 'latest',
    ],
];
