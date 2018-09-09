<?php
use CDash\Test\CDashUseCaseTestCase;
use CDash\Test\UseCase\UseCase;

class UpdateUseCaseTest extends CDashUseCaseTestCase
{
    public function testBuild()
    {
        /** @var \CDash\Test\UseCase\UpdateUseCase $sut */
        $sut = UseCase::createBuilder($this, UseCase::UPDATE);
        $sut->createSite(['Name' => 'sub.domain.tld'])
            ->setBuildName('Linux-g++-0.1-NewFeature_Dev')
            ->setBuildType(UseCase::EXPERIMENTAL)
            ->setUpdateCommand('git fetch')
            ->setRevision($sut->createRevisionHash())
            ->setPriorRevision($sut->createRevisionHash())
            ->setPackages([
                $sut->createPackage(['Doxygen', 'ADoc.i', 'Ricky Bobby']),
                $sut->createPackage(['Doxygen', 'BDoc.i', 'Texas R. Bobby']),
                $sut->createPackage(['Src', 'NewFeature.cxx', 'Ricky Bobby']),
            ]);

        // TODO: of course, make test more robust
        $handler = $sut->build();
        $this->assertInstanceOf(UpdateHandler::class, $handler);
    }

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
        list($date, $time, $tz) = explode(' ', $sut->randomizeCheckinDate());
        $todayDate = date('d');
        list($y, $m, $d) = explode('-', $date);
        // TODO: make this test better
        $this->assertEquals(($todayDate - 1), $d);
    }
}
