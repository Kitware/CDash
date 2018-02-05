<?php
namespace CDash\Test;

use CDash\Database;

class CDashTestCase extends \PHPUnit_Framework_TestCase
{
    protected $mockPDO;
    private $queries;

    /** @var  Database $originalDatabase*/
    private static $originalDatabase;

    public function tearDown()
    {
        global $cdash_database_connection;
        $cdash_database_connection = null;
        parent::tearDown();
    }

    protected function setDatabaseMocked()
    {
        self::$originalDatabase = Database::getInstance();

        $mock_stmt = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'prepare', 'fetch', 'fetchAll', 'fetchColumn'])
            ->getMock();

        $mock_pdo = $this->getMockBuilder(Database::class)
            ->setMethods(
                ['getPdo', 'prepare', 'execute', 'query', 'beginTransaction', 'commit', 'rollBack']
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
     * @return \PHPUnit_Framework_MockObject_MockObject|\Build
     */
    protected function getMockBuild()
    {
        return $this->createMock('Build');
    }

    protected function getMockTest()
    {
        return $this->createMock('Test');
    }

    protected function getMockProject()
    {
        return $this->createMock('Project');
    }

    protected function getMockUser()
    {
        return $this->createMock('User');
    }

    protected function getMockUserProject()
    {
        return $this->createMock('UserProject');
    }

    protected function getMockBuildGroup()
    {
        return $this->createMock('BuildGroup');
    }

    protected function getMockUserTopic()
    {
        return $this->createMock('UserTopic');
    }

    protected function getMockSite()
    {
        return $this->createMock('Site');
    }

    protected function getMockActionableBuild()
    {
        return $this->getMockBuilder('\ActionableBuildInterface')
            ->disableOriginalConstructor()
            ->setMethods(['getActionableBuilds', 'getType', 'getProjectId', 'getBuildGroupId'])
            ->getMock();
    }
}
