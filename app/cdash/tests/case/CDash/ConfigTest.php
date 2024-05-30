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

    public function testGetSet()
    {
        $config = Config::getInstance();
        $config->set('THIS_IS_NOT_A_THING', 'ABCDEFGH');
        $this->assertEquals('ABCDEFGH', $config->get('THIS_IS_NOT_A_THING'));
        $config->set('THIS_IS_NOT_A_THING', null);
    }

    public function testDisablePullRequestComments()
    {
        Log::shouldReceive('info')
            ->with('pull request commenting is disabled');
        RepositoryUtils::post_pull_request_comment(1, 1, "this is a comment", config('app.url'));
    }
}
