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

    public function testGetSet()
    {
        $config = Config::getInstance();
        $config->set('THIS_IS_NOT_A_THING', 'ABCDEFGH');
        $this->assertEquals('ABCDEFGH', $config->get('THIS_IS_NOT_A_THING'));
        $config->set('THIS_IS_NOT_A_THING', null);
    }
}
