<?php
namespace CDash\Collection;

use CDash\Model\Label;
use CDash\Test\CDashTestCase;

class LabelCollectionTest extends CDashTestCase
{
    public function testAdd()
    {
        $labelA = new Label();
        $labelB = new Label();
        $labelC = new Label();

        $labelA->Text = 'Alpha';
        $labelB->Text = 'Bravo';
        $labelC->Text = 'Charlie';

        $sut = new LabelCollection();

        $this->assertNull($sut->get($labelA->Text));
        $this->assertNull($sut->get($labelB->Text));
        $this->assertNull($sut->get($labelC->Text));

        $sut->add($labelA)
            ->add($labelB)
            ->add($labelC);

        $this->assertCount(3, $sut);
        $this->assertSame($labelA, $sut->get($labelA->Text));
        $this->assertSame($labelB, $sut->get($labelB->Text));
        $this->assertSame($labelC, $sut->get($labelC->Text));
    }
}
