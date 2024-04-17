<?php

use App\Utils\RepositoryUtils;
use CDash\Config;
use CDash\Test\CDashTestCase;
use Illuminate\Support\Facades\Log;

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

    public function testDisablePullRequestComments()
    {
        include 'config/config.php';

        Log::shouldReceive('info')
            ->with('pull request commenting is disabled');
        RepositoryUtils::post_pull_request_comment(1, 1, "this is a comment", config('app.url'));
    }
}
