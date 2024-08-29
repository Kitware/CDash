<?php
use CDash\Test\CDashUseCaseTestCase;
use CDash\Test\UseCase\UseCase;

class UpdateUseCaseTest extends CDashUseCaseTestCase
{
    public function testCreateRevisionHash()
    {
        /** @var \CDash\Test\UseCase\UpdateUseCase $sut */
        $sut = UseCase::createBuilder($this, UseCase::UPDATE);

        $expected = 40;
        $actual = strlen($sut->createRevisionHash());
        $this->assertEquals($expected, $actual);
    }

    public function testRandomizeCheckinDate()
    {
        /** @var \CDash\Test\UseCase\UpdateUseCase $sut */
        $sut = UseCase::createBuilder($this, UseCase::UPDATE);
        [$date, $time, $tz] = explode(' ', $sut->randomizeCheckinDate());
        [$y, $m, $d] = explode('-', $date);
        $yesterday = date('d', strtotime('-1 days'));
        $this->assertEquals($yesterday, $d);
    }
}
