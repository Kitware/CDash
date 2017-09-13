<?php

class PHPUnitTestTest extends PHPUnit_Framework_TestCase
{
    public function testAutoloaderAutoloadsModel()
    {
        $this->assertTrue(class_exists('Project'));
        $this->assertFalse(class_exists('GCovTarHandler'));
    }
}
