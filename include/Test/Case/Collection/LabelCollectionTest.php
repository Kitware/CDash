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
        $sut->add($labelA)
            ->add($labelB)
            ->add($labelC);

        $this->assertCount(3, $sut);
        $this->assertSame($labelA, $sut->get('Alpha'));
        $this->assertSame($labelB, $sut->get('Bravo'));
        $this->assertSame($labelC, $sut->get('Charlie'));
    }
}
