<?php
use CDash\Config;
use CDash\Test\CDashTestCase;

class ConfigTest extends CDashTestCase
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
        global $CDASH_CSS_FILE;

        include 'config/config.php';

        $config = Config::getInstance();

        $this->assertEquals($CDASH_CSS_FILE, $config->get('CDASH_CSS_FILE'));
    }

    public function testGetSet()
    {
        $config = Config::getInstance();
        $config->set('THIS_IS_NOT_A_THING', 'ABCDEFGH');
        $this->assertEquals('ABCDEFGH', $config->get('THIS_IS_NOT_A_THING'));
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
        global $CDASH_USE_HTTPS;
        include 'config/config.php';

        $config = Config::getInstance();
        $base_url = config('app.url');

        config(['app.url' => null]);
        $config->set('CDASH_USE_HTTPS', true);
        $_SERVER['SERVER_NAME'] = 'www2.tonyrobins.com';
        $_SERVER['SERVER_PORT'] = 8080;
        $_SERVER['REQUEST_URI'] = '/path/to/success';


        $expected = 'https://www2.tonyrobins.com:8080/path/to/success';
        $actual = $config->getBaseUrl();

        $this->assertEquals($expected, $actual);

        config(['app.url' => 'http://open.cdash.org']);
        $expected = 'http://open.cdash.org';
        $this->assertEquals($expected, $config->getBaseUrl());

        config(['app.url' => $base_url]);
        $config->set('CDASH_USE_HTTPS', $CDASH_USE_HTTPS);
    }

    public function testDisablePullRequestComments()
    {
        include 'config/config.php';
        require_once 'include/repository.php';

        $config = Config::getInstance();
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->with('pull request commenting is disabled');
        post_pull_request_comment(1, 1, "this is a comment", config('app.url'));
    }
}
