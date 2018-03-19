<?php

use CDash\Collection\BuildCollection;
use CDash\Test\CDashUseCaseTestCase;
use CDash\Test\UseCase\ConfigUseCase;
use CDash\Test\UseCase\UseCase;

class ConfigUseCaseTest extends CDashUseCaseTestCase
{
    public function testUseCaseCreateBuilderReturnsInstanceOfConfigUseCase()
    {
        $sut = UseCase::createBuilder($this, UseCase::CONFIG);
        $this->assertInstanceOf(ConfigUseCase::class, $sut);
    }

    public function testConfigUseCaseBuild()
    {
        $sut = UseCase::createBuilder($this, UseCase::CONFIG)
            ->createSite(['Name' => 'Site.name'])
            ->createSubproject('MyExperimentalFeature')
            ->createSubproject(
                'MyProductionCode',
                ['MyProductionCode', 'AnotherLabel']
            )
            ->createSubproject(
                'MyThirdPartyDependency',
                ['MyThirdPartyDependency1', 'MyThirdPartyDependency2']
            )
            ->createSubproject('EmptySubproject')
            ->setStartTime(1469734334)
            ->setEndTime(1469734335)
            ->setConfigureCommand('"/home/betsy/cmake_build/bin/cmake" "-DCTEST_USE_LAUNCHERS=1" "-GUnix Makefiles" "/home/betsy/cmake/Tests/CTestTestSubprojects"')
            ->setConfigureStatus(2)
            ->setConfigureLog('
                This is my configure. There is no other configure like it
                CMake Warning: CMake is forcing CMAKE_CXX_COMPILER to "/usr/bin/c++"
            ');
        $handler = $sut->build();
        $this->assertInstanceOf(ConfigureHandler::class, $handler);

        $buildCollection = $handler->GetBuildCollection();
        $this->assertInstanceOf(BuildCollection::class, $buildCollection);

        $this->assertCount(4, $buildCollection);
        $build = $buildCollection->get('MyExperimentalFeature');
        $buildConfiguration = $build->GetBuildConfigure();

        $build = $buildCollection->get('MyProductionCode');
        $this->assertSame($buildConfiguration, $build->GetBuildConfigure());

        $build = $buildCollection->get('MyThirdPartyDependency');
        $this->assertSame($buildConfiguration, $build->GetBuildConfigure());

        $build = $buildCollection->get('EmptySubproject');
        $this->assertSame($buildConfiguration, $build->GetBuildConfigure());

        $expected = 2;
        $actual = $buildConfiguration->NumberOfErrors;
        $this->assertEquals($expected, $actual);

        $expected = 1;
        $actual = $buildConfiguration->NumberOfWarnings;
        $this->assertEquals($expected, $actual);
    }
}
