<?php

use CDash\Model\Project;
use CDash\Test\CDashTestCase;

class PHPUnitTestTest extends CDashTestCase
{
    public function testAutoloaderAutoloadsModel()
    {
        $this->assertTrue(class_exists(Project::class));
        $this->assertFalse(class_exists('GCovTarHandler'));
    }
}
