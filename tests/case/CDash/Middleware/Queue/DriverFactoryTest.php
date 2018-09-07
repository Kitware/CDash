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


use Bernard\Driver\AppEngineDriver;
use Bernard\Driver\FlatFileDriver;
use Bernard\Driver\PhpRedisDriver;
use Bernard\Driver\PredisDriver;
use Bernard\Driver\SqsDriver;
use CDash\Middleware\Queue\DriverFactory;

class DriverFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testCreateAppEngineDriver()
    {
        $endpoints = ['do-submit' => '/do_submit'];
        $conf = [
            DriverFactory::APP_ENGINE,
            ['queueMap'=> $endpoints],
        ];

        $driver = DriverFactory::create($conf);
        $this->assertInstanceOf(AppEngineDriver::class, $driver);
        $this->assertEquals(['/do_submit' => 'do-submit'], $driver->listQueues());
    }

    public function testCreateDoctrine()
    {
        $conf = [
            DriverFactory::DOCTRINE,
            [
                'memory' => true,
                'driver' => 'pdo_mysql',
                'user' => 'ricky.bobby',
                'password' => 'shake-n-bake',
            ]
        ];

        $driver = DriverFactory::create($conf);
        $this->assertInstanceOf(\Bernard\Driver\DoctrineDriver::class, $driver);
    }

    public function testCreateFlatFile()
    {
        $baseDir = '/path/to/queue/directory';
        $conf = [
            DriverFactory::FLAT_FILE,
            ['baseDirectory' => $baseDir],
        ];
        $driver = DriverFactory::create($conf);
        $this->assertInstanceOf(FlatFileDriver::class, $driver);
    }

    public function testCreatePhpRedis()
    {
        if (extension_loaded('redis')) {
            $conf = [
                DriverFactory::PHP_REDIS,
                ['host' => 'localhost', 'prefix' => 'bernard:'],
            ];
            $driver = DriverFactory::create($conf);
            $this->assertInstanceOf(PhpRedisDriver::class, $driver);
        }
    }

    public function testCreatePredis()
    {
        $conf = [
            DriverFactory::PREDIS,
            ['prefix' => 'bernard:'],
        ];
        $driver = DriverFactory::create($conf);
        $this->assertInstanceOf(PredisDriver::class, $driver);
    }

    public function testCreateSQS()
    {
        $conf = [
            DriverFactory::SQS,
            [
                'profile' => 'CDASH',
                'region' => 'us-east-1',
                'version' => 'latest',
            ],
        ];
        $driver = DriverFactory::create($conf);
        $this->assertInstanceOf(SqsDriver::class, $driver);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage IronMQ Not Implemented
     */
    public function testCreateNotImplemented()
    {
        $conf = [
            DriverFactory::IRON_MQ,
            [],
        ];

        $driver = DriverFactory::create($conf);
    }
}
