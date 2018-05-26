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

namespace CDash\Middleware\Queue;

use Aws\Sqs\SqsClient;
use Bernard\Driver\AppEngineDriver;
use Bernard\Driver\DoctrineDriver;
use Bernard\Driver\FlatFileDriver;
use Bernard\Driver\PhpRedisDriver;
use Bernard\Driver\PredisDriver;
use Bernard\Driver\SqsDriver;
use CDash\Config;
use CDash\Log;
use Doctrine\DBAL\DriverManager;
use Predis\Client as PredisClient;
use Redis;

class DriverFactory
{
    const APP_ENGINE = 'AppEngine';
    const DOCTRINE = 'Doctrine';
    const FLAT_FILE = 'FlatFile';
    const IRON_MQ = 'IronMQ';
    const PHP_REDIS = 'PhpRedis';
    const PREDIS = 'Predis';
    const SQS = 'SQS';

    // Begin NOT Available in Bernard ~0.12
    const MEMORY = 'Memory';
    const INTEROP = 'Interop';
    const MONGO = 'MongoDB';
    const PHEANSTALK = 'Pheanstalk';
    // End NOT Available in Bernard ~0.12

    /**
     * @param null $configuration
     * @return AppEngineDriver|DoctrineDriver|FlatFileDriver|PhpRedisDriver|PredisDriver|SqsDriver|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function create(array $configuration = [])
    {
        if (empty($configuration)) {
            $configuration = static::getConfiguration();
        }

        if (isset($configuration[0]) && isset($configuration[1])) {
            list($key, $properties) = $configuration;
        } else {
            throw new \Exception("Driver not configured.");
        }

        $driver = null;
        switch ($key) {
            case self::APP_ENGINE:
                // Limited functionality
                $driver = new AppEngineDriver($properties['queueMap']);
                break;
            case self::DOCTRINE:
                $connection = DriverManager::getConnection($properties);
                $driver = new DoctrineDriver($connection);
                break;
            case self::FLAT_FILE:
                $driver = new FlatFileDriver($properties['baseDirectory']);
                break;
            case self::PHP_REDIS:
                $redis = new Redis();
                $redis->connect($properties['host']);
                try {
                    $redis->setOption(Redis::OPT_PREFIX, $properties['prefix']);
                } catch (\Exception $e) {
                    Log::getInstance()->error($e);
                }
                $driver = new PhpRedisDriver($redis);
                break;
            case self::PREDIS:
                $client = new PredisClient(null, $properties);
                $driver = new PredisDriver($client);
                break;
            case self::SQS:
                $sqs = new SqsClient($properties);
                $driver = new SqsDriver($sqs);
                break;
            case self::IRON_MQ:
            default:
                throw new \Exception("{$key} Not Implemented.");
        }
        return $driver;
    }

    public static function getConfiguration()
    {
        $config = Config::getInstance();
        $queue_config = $config->load('queue');
        $key = null;
        $filter = function ($v, $k) use (&$key) {
            if ($v['enabled']) {
                $key = $k;
                return true;
            }
        };

        $driver_config = array_filter(
            $queue_config,
            $filter,
            ARRAY_FILTER_USE_BOTH
        );

        unset($driver_config['enabled']);

        return [$key, array_pop($driver_config)];
    }
}
