<?php
namespace CDash\Collection;

use CDash\Model\BuildConfigure;
use CDash\Test\CDashTestCase;

class ConfigureCollectionTest extends CDashTestCase
{
    /*
     * The logic behind the configure collection is that there is only one configure that is shared
     * across many builds. Here we test that only one BuildConfigure is set at any given time.
     */
    public function testAdd()
    {
        $configure1 = new BuildConfigure();
        $configure2 = new BuildConfigure();
        $configure3 = new BuildConfigure();

        $sut = new ConfigureCollection();
        $sut->add($configure1)
            ->add($configure2)
            ->add($configure3);

        $this->assertCount(1, $sut);

        $key = 'Configure';
        $this->assertNotSame($configure1, $sut->get($key));
        $this->assertNotSame($configure2, $sut->get($key));
        $this->assertSame($configure3, $sut->get($key));
    }
}
