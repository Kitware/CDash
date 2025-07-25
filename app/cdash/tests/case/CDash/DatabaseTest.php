<?php

use CDash\Database;
use CDash\Singleton;
use CDash\Test\CDashTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DatabaseTest extends CDashTestCase
{
    public static function setUpBeforeClass(): void
    {
    }

    public static function tearDownAfterClass(): void
    {
        Database::setInstance(Database::class, null);
        parent::tearDownAfterClass();
    }

    public function tearDown(): void
    {
        Database::setInstance(Database::class, null);
        parent::tearDown();
    }

    public function testInstance()
    {
        $db1 = Database::getInstance();
        $this->assertInstanceOf(Database::class, $db1);

        $reflection = new ReflectionClass(Singleton::class);
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

    public function testGetPdoReturnsInstanceOfPDO()
    {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $this->assertInstanceOf('PDO', $pdo);
    }

    public function testExecute()
    {
        $input_params = ['param1', 'param2'];
        /** @var PDOStatement|MockObject $stmt */
        $stmt = $this->getMockBuilder('\PDOStatement')
            ->getMock();
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($input_params));

        $db = Database::getInstance();
        $db->execute($stmt, $input_params);
    }
}
