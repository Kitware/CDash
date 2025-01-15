<?php

/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$
  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.
  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

namespace CDash\Test;

use CDash\Database;
use CDash\Model\Build;
use CDash\ServiceContainer;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

require_once 'include/common.php';

class CDashTestCase extends TestCase
{
    protected $mockPDO;

    /** @var Database */
    private $originalDatabase;

    public static function tearDownAfterClass(): void
    {
        ServiceContainer::setInstance(ServiceContainer::class, null);
        parent::tearDownAfterClass();
    }

    public function tearDown(): void
    {
        global $cdash_database_connection;
        $cdash_database_connection = null;
        if ($this->originalDatabase) {
            Database::setInstance(Database::class, $this->originalDatabase);
        }
        parent::tearDown();
    }

    protected function setDatabaseMocked()
    {
        $this->originalDatabase = Database::getInstance();

        $mock_stmt = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'fetch', 'fetchAll', 'fetchColumn', 'bindParam', 'bindValue', 'rowCount', 'closeCursor'])
            ->getMock();

        $mock_pdo = $this->getMockBuilder(Database::class)
            ->onlyMethods(
                ['getPdo', 'prepare', 'execute', 'query']
            )
            ->getMock();

        $mock_pdo
            ->expects($this->any())
            ->method('getPdo')
            ->willReturnSelf();

        $mock_pdo
            ->expects($this->any())
            ->method('prepare')
            ->willReturn($mock_stmt);

        $mock_pdo
            ->expects($this->any())
            ->method('query')
            ->willReturn($mock_stmt);

        Database::setInstance(Database::class, $mock_pdo);
    }

    protected function createMockFromBuilder($className)
    {
        return $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject|Build
     */
    protected function getMockBuild()
    {
        return $this->createMockFromBuilder(Build::class);
    }
}
