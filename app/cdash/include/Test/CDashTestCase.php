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

use App\Models\Test;
use App\Models\User;

use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\UserProject;
use CDash\ServiceContainer;
use DI\Container;
use DI\ContainerBuilder;

use Tests\TestCase;

class CDashTestCase extends TestCase
{
    protected $mockPDO;
    private $queries;

    /** @var  Database $originalDatabase*/
    private $originalDatabase;

    /** @var Container $originalServiceContainer */
    private static $originalServiceContainer;

    /** @var String $endpoint */
    private $endpoint;

    public static function tearDownAfterClass()
    {
        ServiceContainer::setInstance(ServiceContainer::class, self::$originalServiceContainer);
        parent::tearDownAfterClass();
    }

    public function tearDown() : void
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

    protected function createMockFromBuilder($className)
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
        return $this->createMockFromBuilder(Build::class);
    }

    protected function getMockTest()
    {
        return $this->createMockFromBuilder(Test::class);
    }

    protected function getMockProject()
    {
        return $this->createMockFromBuilder(Project::class);
    }

    protected function getMockUser()
    {
        return $this->createMockFromBuilder(User::class);
    }

    protected function getMockUserProject()
    {
        return $this->createMockFromBuilder(UserProject::class);
    }

    protected function getMockBuildGroup()
    {
        return $this->createMockFromBuilder(BuildGroup::class);
    }

    protected function getMockSite()
    {
        return $this->createMockFromBuilder(Site::class);
    }

    protected function getMockActionableBuild()
    {
        return $this->getMockBuilder('\ActionableBuildInterface')
            ->disableOriginalConstructor()
            ->setMethods(['getActionableBuilds', 'getType', 'getProjectId', 'getBuildGroupId'])
            ->getMock();
    }

    protected function setEndpoint($endpoint)
    {
        $config = Config::getInstance();
        $root = $config->get('CDASH_ROOT_DIR');
        $this->endpoint = realpath("{$root}/public/api/v1/{$endpoint}.php");
        if (!$this->endpoint) {
            throw new \Exception('Endpoint does not exist');
        }
    }
    protected function getEndpointResponse()
    {
        $response = null;

        ob_start();
        if ($this->endpoint) {
            try {
                require $this->endpoint;
            } catch (\Exception $exception) {
                //
            }
            $response = ob_get_contents();
            ob_end_clean();
        } else {
            throw new \Exception('Endpoint not set');
        }

        return json_decode($response);
    }
}
