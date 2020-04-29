<?php

use CDash\Database;
use CDash\Config;
use CDash\Log;
use CDash\Test\CDashTestCase;
use CDash\Test\Log as TestLog;

class DatabaseTest extends CDashTestCase
{
    private static $_backup;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        $log = new TestLog();
        Log::setInstance(Log::class, $log);
    }

    public static function tearDownAfterClass()
    {
        Config::setInstance(Config::class, null);
        Log::setInstance(Log::class, null);
        Database::setInstance(Database::class, null);
        parent::tearDownAfterClass();
    }

    public function tearDown() : void
    {
        Database::setInstance(Database::class, null);
        parent::tearDown();
    }

    public function testInstance()
    {
        $db1 = Database::getInstance();
        $this->assertInstanceOf(Database::class, $db1);

        $reflection = new ReflectionClass(\CDash\Singleton::class);
        $property = $reflection->getProperty('_instances');
        $property->setAccessible(true);
        $instances = $property->getValue();

        $this->assertSame($instances[Database::class], $db1);

        $db2 = Database::getInstance();
        $this->assertSame($db1, $db2);
    }

    public function testGetPdo()
    {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $this->assertNotFalse($pdo);
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testGetPdoReturnsInstanceOfDBALSymphonyDriver()
    {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $this->assertInstanceOf('Doctrine\DBAL\Driver\PDOConnection', $pdo);
    }

    public function testExecute()
    {
        $input_params = ['param1', 'param2'];
        /** @var PDOStatement|PHPUnit_Framework_MockObject_MockObject $stmt */
        $stmt = $this->getMockBuilder('\PDOStatement')
            ->getMock();
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($input_params));

        $db = Database::getInstance();
        $db->execute($stmt, $input_params);
    }

    public function testExecuteStatementLogsRuntimeException()
    {
        $input_params = ['param1', 'param2'];
        $message = 'This is an exceptional message';
        $exception = new \Doctrine\DBAL\Driver\PDOException(new PDOException($message));

        /** @var PDOStatement|PHPUnit_Framework_MockObject_MockObject $stmt */
        $stmt = $this->getMockBuilder('\PDOStatement')
            ->getMock();
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($input_params))
            ->willThrowException($exception);

        $stmt
            ->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['54321', '12345', 'This is an exceptional message' ]);

        $db = Database::getInstance();
        $db->execute($stmt, $input_params);

        $log = Log::getInstance()->getLogEntries();
        $this->assertContains('This is an exceptional message', $log[0]['message']);
    }
}
