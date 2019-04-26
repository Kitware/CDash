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
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\Test;
use CDash\Model\User;
use CDash\Model\UserProject;
use CDash\ServiceContainer;
use DI\Container;
use DI\ContainerBuilder;

class CDashTestCase extends \PHPUnit_Framework_TestCase
{
    protected $mockPDO;
    private $queries;

    /** @var  Database $originalDatabase*/
    private $originalDatabase;

    /** @var Container $originalServiceContainer */
    private static $originalServiceContainer;

    public static function tearDownAfterClass()
    {
        ServiceContainer::setInstance(ServiceContainer::class, self::$originalServiceContainer);
        parent::tearDownAfterClass();
    }

    public function tearDown()
    {
        global $cdash_database_connection;
        $cdash_database_connection = null;
        if ($this->originalDatabase) {
            Database::setInstance(Database::class, $this->originalDatabase);
        }
        parent::tearDown();
    }

    protected function createServiceContainerForTesting()
    {
        $service = ServiceContainer::getInstance();
        self::$originalServiceContainer = $service->getContainer();

        $builder = new ContainerBuilder();
        $builder->useAutowiring(false);
        $builder->useAnnotations(false);
        $container = $builder->build();
        $service->setContainer($container);
    }

    protected function setDatabaseMocked()
    {
        $this->originalDatabase = Database::getInstance();

        $mock_stmt = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'prepare', 'fetch', 'fetchAll', 'fetchColumn'])
            ->getMock();

        $mock_pdo = $this->getMockBuilder(Database::class)
            ->setMethods(
                ['getPdo', 'prepare', 'execute', 'query', 'beginTransaction', 'commit', 'rollBack', 'quote']
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

        $mock_pdo
            ->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function ($arg) {
                return "'" . $arg . "'";
            })
        );

        Database::setInstance(Database::class, $mock_pdo);
    }

    protected function getMockStmt()
    {
        return $this->getMockBuilder('PDOStatement')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function prepare($query)
    {
        $stmt = $this->getMockStmt();
        $execute = false;
        $hash = $this->hashQuery($query);
        if (isset($this->queries[$hash])) {
            $execute = true;

            $records = $this->queries[$hash];

            $stmt
                ->expects($this->any())
                ->method('fetchAll')
                ->willReturn($records);

            $stmt
                ->expects($this->any())
                ->method('fetch')
                ->willReturn($records);

            $stmt
                ->expects($this->any())
                ->method('fetchColumn')
                ->willReturn($records);
        }

        $stmt
            ->expects($this->any())
            ->method('execute')
            ->willReturn($execute);

        return $stmt;
    }

    public function mockFetchCall($query, $records)
    {
        $hash = $this->hashQuery($query);

        $this->queries[$hash] = $records;
    }

    private function hashQuery($query)
    {
        $hash = preg_replace('/\s+/', ' ', $query);
        return md5($hash);
    }

    protected function createMock($className)
    {
        return $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Build
     */
    protected function getMockBuild()
    {
        return $this->createMock(Build::class);
    }

    protected function getMockTest()
    {
        return $this->createMock(Test::class);
    }

    protected function getMockProject()
    {
        return $this->createMock(Project::class);
    }

    protected function getMockUser()
    {
        return $this->createMock(User::class);
    }

    protected function getMockUserProject()
    {
        return $this->createMock(UserProject::class);
    }

    protected function getMockBuildGroup()
    {
        return $this->createMock(BuildGroup::class);
    }

    protected function getMockSite()
    {
        return $this->createMock(Site::class);
    }

    protected function getMockActionableBuild()
    {
        return $this->getMockBuilder('\ActionableBuildInterface')
            ->disableOriginalConstructor()
            ->setMethods(['getActionableBuilds', 'getType', 'getProjectId', 'getBuildGroupId'])
            ->getMock();
    }
}
