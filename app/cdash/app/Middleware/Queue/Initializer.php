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

class Initializer
{
    protected $dbalConnection;
    protected $schema;
    protected $schemaManager;

    public function __construct()
    {
        $this->dbalConnection = null;
        $this->schema = null;
        $this->schemaManager = null;
    }

    /**
     * @param string $key
     * @param array $properties
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    public function initialize($key, array $properties)
    {
        if (config('app.debug')) {
            return;
        }

        switch ($key) {
            case DriverFactory::DOCTRINE:
                // Create required tables if they do not already exist.
                $tables = ['bernard_messages', 'bernard_queues'];
                $connection = $this->getDbalConnection($properties);
                $schemaManager = $this->getSchemaManager($connection);
                $schema = $this->getSchema();
                if (!$schemaManager->tablesExist($tables)) {
                    MessagesSchema::create($schema);
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

    public function getDbalConnection(array $properties)
    {
        if (!$this->dbalConnection) {
            $this->dbalConnection = DriverManager::getConnection($properties);
        }
        return $this->dbalConnection;
    }

    public function setDbalConnection(\Doctrine\DBAL\Connection $connection)
    {
        $this->dbalConnection = $connection;
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $connection)
    {
        if (!$this->schemaManager) {
            $this->schemaManager = $connection->getSchemaManager();
        }
        return $this->schemaManager;
    }

    public function setSchemaManager(\Doctrine\DBAL\Schema\AbstractSchemaManager $manager)
    {
        $this->schemaManager = $manager;
    }

    public function getSchema()
    {
        if (!$this->schema) {
            $this->schema = new Schema();
        }
        return $this->schema;
    }

    public function setSchema(Schema $schema)
    {
        $this->schema = $schema;
    }
}
