<?php
use CDash\Config;
use CDash\Singleton;

class ConfigTest extends PHPUnit_Framework_TestCase
{
    public function testGetInstance()
    {
        $config = Config::getInstance();
        $this->assertInstanceOf(Config::class, $config);

        $reflection = new ReflectionClass(\CDash\Singleton::class);
        $property = $reflection->getProperty('_instances');
        $property->setAccessible(true);
        $instances = $property->getValue();

        $this->assertSame($instances[Config::class], $config);
    }

    public function testConstruction()
    {
        // check some random values to ensure that they match that of the Config instance
        global $CDASH_CSS_FILE,
               $CDASH_UPLOAD_DIRECTORY,
               $CDASH_ACTIVE_PROJECT_DAYS,
               $CDASH_FORWARDING_IP;

        include 'config/config.php';

        $config = Config::getInstance();

        $this->assertEquals($CDASH_CSS_FILE, $config->get('CDASH_CSS_FILE'));
        $this->assertEquals($CDASH_UPLOAD_DIRECTORY, $config->get('CDASH_UPLOAD_DIRECTORY'));
        $this->assertEquals($CDASH_ACTIVE_PROJECT_DAYS, $config->get('CDASH_ACTIVE_PROJECT_DAYS'));
        $this->assertEquals($CDASH_FORWARDING_IP, $config->get('CDASH_FORWARDING_IP'));
    }

    public function testGetSet()
    {
        $config = Config::getInstance();
        $testing_mode = $config->get('CDASH_TESTING_MODE');
        $production_mode = $config->get('CDASH_PRODUCTION_MODE');
        $config->set('CDASH_TESTING_MODE', '5544332211abc');
        $config->set('CDASH_PRODUCTION_MODE', 'abcd22334455');
        $config->set('THIS_IS_NOT_A_THING', 'ABCDEFGH');

        $this->assertEquals('5544332211abc', $config->get('CDASH_TESTING_MODE'));
        $this->assertEquals('abcd22334455', $config->get('CDASH_PRODUCTION_MODE'));
        $this->assertEquals('ABCDEFGH', $config->get('THIS_IS_NOT_A_THING'));

        $config->set('CDASH_TESTING_MODE', $testing_mode);
        $config->set('CDASH_PRODUCTION_MODE', $production_mode);
        $config->set('THIS_IS_NOT_A_THING', null);
    }

    public function testGetServer()
    {
        $config = Config::getInstance();
        $backup = $config->get('CDASH_SERVER_NAME');
        $_SERVER['SERVER_NAME'] = 'this.is.my.server';

        $expected = 'a.server';
        $config->set('CDASH_SERVER_NAME', $expected);
        $actual = $config->getServer();

        $this->assertEquals($expected, $actual);

        $config->set('CDASH_SERVER_NAME', '');
        $actual = $config->getServer();

        $this->assertEquals($_SERVER['SERVER_NAME'], $actual);

        $config->set('CDASH_SERVER_NAME', $backup);
    }

    public function testGetProtocol()
    {
        $config = Config::getInstance();
        $backup = $config->get('CDASH_USE_HTTPS');
        $_SERVER['SERVER_PORT'] = '';

        $expected = 'http';
        $config->set('CDASH_USE_HTTPS', false);
        $actual = $config->getProtocol();

        $this->assertEquals($expected, $actual);

        $expected = 'https';
        $config->set('CDASH_USE_HTTPS', true);
        $actual = $config->getProtocol();

        $this->assertEquals($expected, $actual);

        $_SERVER['SERVER_PORT'] = 443;
        $config->set('CDASH_USE_HTTPS', false);
        $actual = $config->getProtocol();
        $this->assertEquals($expected, $actual);

        $config->set('CDASH_USE_HTTPS', $backup);
    }

    public function testGetServerPort()
    {
        $config = Config::getInstance();
        $_SERVER['SERVER_PORT'] = 80;
        $this->assertNull($config->getServerPort());

        $_SERVER['SERVER_PORT'] = 443;
        $this->assertNull($config->getServerPort());

        $_SERVER['SERVER_PORT'] = 8080;
        $this->assertEquals(8080, $config->getServerPort());
    }

    public function testGetBaseUrl()
    {
        global $CDASH_BASE_URL, $CDASH_USE_HTTPS;
        include 'config/config.php';

        $config = Config::getInstance();
        $actual = $config->getBaseUrl();

        $this->assertEquals($CDASH_BASE_URL, $actual);

        $config->set('CDASH_BASE_URL', null);
        $config->set('CDASH_USE_HTTPS', true);
        $_SERVER['SERVER_NAME'] = 'www2.tonyrobins.com/';
        $_SERVER['SERVER_PORT'] = 8080;
        $_SERVER['REQUEST_URI'] = '/path/to/success';


        $expected = 'https://www2.tonyrobins.com:8080/path/to/success';
        $actual = $config->getBaseUrl();

        $this->assertEquals($expected, $actual);

        $config->set('CDASH_BASE_URL', $CDASH_BASE_URL);
        $config->set('CDASH_USE_HTTPS', $CDASH_USE_HTTPS);
    }


    /**
     * @expectedException        Exception
     * @expectedExceptionMessage pull request commenting is disabled
     */
    public function testDisablePullRequestComments()
    {
        include 'config/config.php';
        require_once 'include/repository.php';

        $config = Config::getInstance();
        $config->set('CDASH_NOTIFY_PULL_REQUEST', false);
        $config->set('CDASH_TESTING_MODE', true);

        post_pull_request_comment(1, 1, "this is a comment", $config->get('CDASH_BASE_URL'));
    }
}
