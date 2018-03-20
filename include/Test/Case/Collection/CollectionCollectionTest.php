<?php
namespace CDash\Collection;

class CollectionCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $build = new BuildCollection();
        $configure = new ConfigureCollection();
        $label = new LabelCollection();

        $sut = new CollectionCollection();
        $sut->add($build)
            ->add($configure)
            ->add($label);

        $this->assertSame($build, $sut->get(BuildCollection::class));
        $this->assertSame($configure, $sut->get(ConfigureCollection::class));
        $this->assertSame($label, $sut->get(LabelCollection::class));
    }
}
