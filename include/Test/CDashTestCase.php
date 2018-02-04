<?php
namespace CDash\Test;

class CDashTestCase extends \PHPUnit_Framework_TestCase
{
    protected $mockPDO;
    private $queries;

    public static function buildUseCase()
    {
        return new UseCase();
    }

    public function setUp()
    {
        parent::setUp();
        global $cdash_database_connection;

        $this->queries = [];

        $database = $this->getMockBuilder('CDash\Database')
            ->disableOriginalConstructor()
            ->setMethods(['getPdo'])
            ->getMock();

        $this->mockPDO = $this->getMockBuilder('CDash\Test\MockPDO')
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockPDO
            ->expects($this->any())
            ->method('prepare')
            ->willReturnCallback([$this, 'prepare']);

        $this->mockPDO
            ->expects($this->any())
            ->method('query')
            ->willReturnCallback([$this, 'prepare']);

        $database
            ->expects($this->any())
            ->method('getPdo')
            ->willReturn($this->mockPDO);

        $cdash_database_connection = $database;
    }

    public function tearDown()
    {
        global $cdash_database_connection;
        $cdash_database_connection = null;
        parent::tearDown();
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
