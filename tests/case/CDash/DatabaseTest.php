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

    public function tearDown()
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

    public function testGetPdoReturnsFalseWhenUnableToConnect()
    {
        $config = Config::getInstance();
        $config->set('CDASH_DB_NAME', 'my_abcd_database');
        $config->set('CDASH_DB_HOST', 'localhost');
        $config->set('CDASH_DB_PORT', '3307');
        $config->set('CDASH_DB_PASS', 'ABCD123abcd!');
        $config->set('CDASH_DB_TYPE', Database::DB_TYPE_PGSQL);
        $config->set('CDASH_DB_LOGIN', 'thumper10-51-1');
        $config->set('CDASH_DB_CONNECTION_TYPE', Database::DB_CONNECTION_TYPE_HOST);

        $db = Database::getInstance();
        $pdo = $db->getPdo();

        $this->assertFalse($pdo);

        // Test that exception is being logged
        $log = Log::getInstance();
        $entries = $log->getLogEntries();

        $expected = 'could not connect to server';
        $actual = $entries[0]['message'];
        $this->assertContains($expected, $actual);

        $expected = LOG_ERR;
        $actual = $entries[0]['level'];

        $this->assertEquals($expected, $actual);

        // Test that exception does not get logged
        $log->clear();
        $pdo = $db->getPdo(false);
        $this->assertFalse($pdo);
        $this->assertEmpty($log->getLogEntries());
    }

    public function testBuildDsn()
    {
        $config = Config::getInstance();
        $config->set('CDASH_DB_NAME', 'my_abcd_database');
        $config->set('CDASH_DB_HOST', 'cdash.someserver.dev');
        $config->set('CDASH_DB_PORT', null);
        $config->set('CDASH_DB_PASS', 'ABCD123abcd!');
        $config->set('CDASH_DB_TYPE', Database::DB_TYPE_PGSQL);
        $config->set('CDASH_DB_LOGIN', 'thumper10-51-1');
        $config->set('CDASH_DB_CONNECTION_TYPE', Database::DB_CONNECTION_TYPE_HOST);

        $expected = 'pgsql:host=cdash.someserver.dev';

        $db = Database::getInstance();
        $actual = $db->buildDsn();

        $this->assertEquals($expected, $actual);

        // Add a port to the configuration, ensure port is included in dsn
        $config->set('CDASH_DB_PORT', '3307');
        $expected = 'pgsql:host=cdash.someserver.dev;port=3307';

        Database::setInstance(Database::class, null);
        $db = Database::getInstance();
        $actual = $db->buildDsn();

        // Pass database name as argument to buildDsn, ensure name is included in dsn
        $this->assertEquals($expected, $actual);
        $expected = 'pgsql:host=cdash.someserver.dev;port=3307;dbname=my_abcd_database';
        $actual = $db->buildDsn($config->get('CDASH_DB_NAME'));

        $this->assertEquals($expected, $actual);

        // Change the database type to MySQL, ensure dns is properly formed
        $config->set('CDASH_DB_TYPE', Database::DB_TYPE_MYSQL);
        Database::setInstance(Database::class, null);
        $db = Database::getInstance();

        $expected = 'mysql:host=cdash.someserver.dev;port=3307;dbname=my_abcd_database';
        $actual = $db->buildDsn($config->get('CDASH_DB_NAME'));

        $this->assertEquals($expected, $actual);

        // Change connection type to unix_socket, ensure that port is not set on dsn
        Database::setInstance(Database::class, null);
        $config->set('CDASH_DB_CONNECTION_TYPE', Database::DB_CONNECTION_TYPE_SOCKET);
        $config->set('CDASH_DB_HOST', '/tmp/mysql.sock');
        $db = Database::getInstance();

        $expected = 'mysql:unix_socket=/tmp/mysql.sock';
        $actual = $db->buildDsn();

        $this->assertEquals($expected, $actual);
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

        /** @var PDOStatement|PHPUnit_Framework_MockObject_MockObject $stmt */
        $stmt = $this->getMockBuilder('\PDOStatement')
            ->getMock();
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($input_params))
            ->willReturn(false);

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
