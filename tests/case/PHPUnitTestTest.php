<?php

use CDash\Model\Project;

class PHPUnitTestTest extends PHPUnit_Framework_TestCase
{
    public function testAutoloaderAutoloadsModel()
    {
        $this->assertTrue(class_exists(Project::class));
        $this->assertFalse(class_exists('GCovTarHandler'));
    }
}
