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

use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\ServiceContainer;

use Tests\TestCase;

require_once 'include/common.php';

class CDashTestCase extends TestCase
{
    protected $mockPDO;

    /** @var  Database $originalDatabase*/
    private $originalDatabase;

    /** @var String $endpoint */
    private $endpoint;

    public static function tearDownAfterClass() : void
    {
        ServiceContainer::setInstance(ServiceContainer::class, null);
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

    protected function setDatabaseMocked()
    {
        $this->originalDatabase = Database::getInstance();

        $mock_stmt = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'prepare', 'fetch', 'fetchAll', 'fetchColumn', 'bindParam', 'bindValue', 'rowCount', 'closeCursor'])
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
