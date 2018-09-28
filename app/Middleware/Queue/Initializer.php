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

use Bernard\Doctrine\MessagesSchema;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;

use CDash\Config;

class Initializer
{
    /**
     * @param string $key
     * @param array $properties
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function initialize($key, array $properties)
    {
        if (Config::getInstance()->get('CDASH_TESTING_MODE')) {
            return;
        }

        switch ($key) {
            case DriverFactory::DOCTRINE:
                $connection = DriverManager::getConnection($properties);
                // Create required tables if they do not already exist.
                $tables = ['bernard_messages', 'bernard_queues'];
                if (!$connection->getSchemaManager()->tablesExist($tables)) {
                    MessagesSchema::create($schema = new Schema);
                    $sql = $schema->toSql($connection->getDatabasePlatform());
                    foreach ($sql as $query) {
                        $connection->exec($query);
                    }
                }
                break;

            case DriverFactory::FLAT_FILE:
                if (!file_exists($properties['baseDirectory'])) {
                    mkdir($properties['baseDirectory']);
                }
                break;

            case DriverFactory::APP_ENGINE:
            case DriverFactory::IRON_MQ:
            case DriverFactory::PHP_REDIS:
            case DriverFactory::PREDIS:
            case DriverFactory::SQS:
            default:
                return;
        }
    }
}
