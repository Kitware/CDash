<?php

use CDash\Collection\BuildCollection;
use CDash\Test\CDashUseCaseTestCase;
use CDash\Test\UseCase\ConfigUseCase;
use CDash\Test\UseCase\UseCase;

class ConfigUseCaseTest extends CDashUseCaseTestCase
{
    private $cmd = '"/home/betsy/cmake_build/bin/cmake" "-DCTEST_USE_LAUNCHERS=1" "-GUnix Makefiles" "/home/betsy/cmake/Tests/CTestTestSubprojects"';
    private $log = '
        This is my configure. There is no other configure like it
         CMake Warning: CMake is forcing CMAKE_CXX_COMPILER to "/usr/bin/c++"
    ';
    private $errors = 2;
    private $warnings = 1;

    public function testUseCaseCreateBuilderReturnsInstanceOfConfigUseCase()
    {
        $sut = UseCase::createBuilder($this, UseCase::CONFIG);
        $this->assertInstanceOf(ConfigUseCase::class, $sut);
    }

    private function checkConfigureForSameness(BuildConfigure $configure)
    {
        $this->assertEquals($this->errors, $configure->NumberOfErrors);
        $this->assertEquals($this->warnings, $configure->NumberOfWarnings);
        $this->assertEquals($this->log, $configure->Log);
        $this->assertEquals($this->cmd, $configure->Command);
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
            ->setConfigureCommand($this->cmd)
            ->setConfigureStatus($this->errors)
            ->setConfigureLog($this->log);
        $handler = $sut->build();
        $this->assertInstanceOf(ConfigureHandler::class, $handler);

        $buildCollection = $handler->GetBuildCollection();
        $this->assertInstanceOf(BuildCollection::class, $buildCollection);

        $this->assertCount(4, $buildCollection);
        $build = $buildCollection->get('MyExperimentalFeature');
        $buildConfiguration = $build->GetBuildConfigure();
        $this->checkConfigureForSameness($buildConfiguration);

        $build = $buildCollection->get('MyProductionCode');
        $buildConfiguration = $build->GetBuildConfigure();
        $this->checkConfigureForSameness($buildConfiguration);

        $build = $buildCollection->get('MyThirdPartyDependency');
        $buildConfiguration = $build->GetBuildConfigure();
        $this->checkConfigureForSameness($buildConfiguration);

        $build = $buildCollection->get('EmptySubproject');
        $buildConfiguration = $build->GetBuildConfigure();
        $this->checkConfigureForSameness($buildConfiguration);
    }
}
