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

use CDash\Middleware\Queue\DriverFactory as Driver;
use CDash\Test\CDashTestCase;

class InitializerTest extends CDashTestCase
{
    public function setUp()
    {
        parent::setUp();
        \CDash\Config::getInstance()->set('CDASH_TESTING_MODE', false);
    }

    public function testInitializeFlatFile()
    {
        $queue_dir = \CDash\Config::getInstance()->get('CDASH_BACKUP_DIRECTORY')
            .  DIRECTORY_SEPARATOR . 'queue_initializer_dir';
        $this->assertFileNotExists($queue_dir);

        $sut = new Initializer();
        $sut->initialize(Driver::FLAT_FILE, ['baseDirectory' => $queue_dir]);

        $this->assertFileExists($queue_dir);
        rmdir($queue_dir);
    }

    public function testInitializeDoctrine()
    {
        $mock_table = $this->getMockBuilder(\Doctrine\DBAL\Schema\Table::class)
            ->disableOriginalConstructor()
            ->setMethods(['addColumn', 'addIndex', 'setPrimaryKey'])
            ->getMock();

        $mock_schema = $this->getMockBuilder(\Doctrine\DBAL\Schema\Schema::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock_schema
            ->expects($this->any())
            ->method('toSql')
            ->willReturn(['foo', 'bar']);
        $mock_schema
            ->expects($this->any())
            ->method('createTable')
            ->willReturn($mock_table);

        $mock_platform = $this->getMockBuilder(\Doctrine\DBAL\Platforms\AbstractPlatform::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $mock_connection = $this->getMockBuilder(\Doctrine\DBAL\Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock_connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($mock_platform);
        $mock_connection->expects($this->exactly(2))
            ->method('exec')
            ->with($this->logicalOr('foo', 'bar'));

        $mock_schema_manager = $this->getMockBuilder(\Doctrine\DBAL\Schema\AbstractSchemaManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['tablesExist'])
            ->getMockForAbstractClass();
        $mock_schema_manager->expects($this->once())
            ->method('tablesExist')
            ->with(['bernard_messages', 'bernard_queues'])
            ->willReturn(false);

        $sut = new Initializer();
        $sut->setDbalConnection($mock_connection);
        $sut->setSchema($mock_schema);
        $sut->setSchemaManager($mock_schema_manager);

        $properties = [
            'dbname' => 'mock_db',
            'user' => 'mock_user',
            'password' => 'mock_password',
            'host' => 'localhost',
            'driver' => 'pdo_mysql'
        ];
        $sut->initialize(Driver::DOCTRINE, $properties);
    }

    public function testInitializeNoOp()
    {
        $sut = new Initializer();
        $sut->initialize(Driver::IRON_MQ, []);
    }
}
