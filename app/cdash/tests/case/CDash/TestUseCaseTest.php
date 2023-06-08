<?php

use CDash\Model\Build;
use CDash\Collection\BuildCollection;
use CDash\Test\CDashUseCaseTestCase;
use CDash\Test\UseCase\TestUseCase;
use CDash\Test\UseCase\UseCase;

class TestUseCaseTest extends CDashUseCaseTestCase
{
    public function testUseCaseBuildsTestUseCase()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);
        $this->assertInstanceOf(TestUseCase::class, $sut);
    }

    public function testBuildThrowsExceptionIfNameNotSet()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Site properties not initialized');
        $sut->build();
    }

    public function testTestUseCaseReturnsTestingHandler()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);
        $sut->createSite(['Name' => 'Site.Name']);
        /** @var ActionableBuildInterface $handler */
        $handler = $sut->build();
        $this->assertInstanceOf(\TestingHandler::class, $handler);
    }

    public function testTestUseCaseCreatesBuildAndSiteInformation()
    {
        $siteInformation = [
            'BuildName' => 'CTestTest-Linux-c++-Subprojects',
            'BuildStamp' => '20160728-1932-Experimental',
            'Name' => 'livonia-linux',
            'Generator' => 'ctest-3.6.20160726-g3e55f-dirty',
            'CompilerName' => 'g++',
            'CompilerVersion' => 'Linux LLVM version 3.4.5',
            'OSName' => 'Linux',
            'Hostname' => 'livonia-linux',
            'OSRelease' => '3.13.0-35-generic',
            'OSVersion' => '#62-Ubuntu SMP Fri Aug 15 01:58:42 UTC 2014',
            'OSPlatform' => 'x86_64',
            'Is64Bits' => '1',
            'VendorString' => 'GenuineIntel',
            'VendorID' => 'Intel Corporation',
            'FamilyID' => '6',
            'ModelID' => '69',
            'ProcessorCacheSize' => '4096',
            'NumberOfLogicalCPU' => '4',
            'NumberOfPhysicalCPU' => '1',
            'TotalVirtualMemory' => '7627',
            'TotalPhysicalMemory' => '7890',
            'LogicalProcessorsPerPhysical' => '4',
            'ProcessorClockFrequency' => '756',
            'Description' => 'Linux builds on livonia',
        ];

        $sut = UseCase::createBuilder($this, UseCase::TEST)
            ->createSite($siteInformation);
        $handler = $sut->build();
        $builds = $handler->GetBuildCollection();
        /** @var Build $build */
        $build = $builds->current();
        $information = $build->GetSite()->mostRecentInformation;

        $this->assertEquals($information->description, $siteInformation['Description']);
        $this->assertEquals($information->processoris64bits, $siteInformation['Is64Bits']);
        $this->assertEquals($information->processorvendor, $siteInformation['VendorString']);
        $this->assertEquals($information->processorvendorid, $siteInformation['VendorID']);
        $this->assertEquals($information->processorfamilyid, $siteInformation['FamilyID']);
        $this->assertEquals($information->processormodelid, $siteInformation['ModelID']);
        $this->assertEquals(
            $information->processorcachesize,
            $siteInformation['ProcessorCacheSize']
        );
        $this->assertEquals(
            $information->numberlogicalcpus,
            $siteInformation['NumberOfLogicalCPU']
        );
        $this->assertEquals(
            $information->numberphysicalcpus,
            $siteInformation['NumberOfPhysicalCPU']
        );
        $this->assertEquals(
            $information->totalvirtualmemory,
            $siteInformation['TotalVirtualMemory']
        );
        $this->assertEquals(
            $information->totalphysicalmemory,
            $siteInformation['TotalPhysicalMemory']
        );
        $this->assertEquals(
            $information->logicalprocessorsperphysical,
            $siteInformation['LogicalProcessorsPerPhysical']
        );
        $this->assertEquals(
            $information->processorclockfrequency,
            $siteInformation['ProcessorClockFrequency']
        );
        $this->assertEquals($build->Information->OSName, $siteInformation['OSName']);
        $this->assertEquals($build->Information->OSRelease, $siteInformation['OSRelease']);
        $this->assertEquals($build->Information->OSVersion, $siteInformation['OSVersion']);
        $this->assertEquals($build->Information->OSPlatform, $siteInformation['OSPlatform']);
        $this->assertEquals($build->Information->CompilerName, $siteInformation['CompilerName']);
        $this->assertEquals(
            $build->Information->CompilerVersion,
            $siteInformation['CompilerVersion']
        );
    }

    public function testTestUseCaseSetsSiteProperty()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);
        $useCaseReflection = new ReflectionClass(TestUseCase::class);
        $property = $useCaseReflection->getProperty('properties');
        $property->setAccessible(true);
        $actual = $property->getValue($sut);
        $this->assertArrayHasKey('Test', $actual);
        $this->assertEmpty($actual['Test']);

        $sut->setSiteAttribute('BuildStamp', '20180125-1724-Experimental');
        // TODO: This is not what I would expect this structure to look like, Test -> [Site -> []]
        $expected = ['Site' => [['BuildStamp' => '20180125-1724-Experimental']], 'Test' => []];
        $actual = $property->getValue($sut);
        $this->assertEquals($expected, $actual);
    }

    public function testTestUseCaseCreatesSubproject()
    {
        /** @var UseCase $sut */
        $sut = UseCase::createBuilder($this, UseCase::TEST);
        $sut->createSite(['Name' => 'Site.Name'])
            ->createSubproject('NOX', ['Non-linear', 'Transient', 'Optimization'])
            ->createSubproject('Teuchos', ['Linear']);

        /** @var TestingHandler $handler */
        $handler = $sut->build();

        /** @var BuildCollection $builds */
        $builds = $handler->GetBuildCollection();

        $this->assertInstanceOf(BuildCollection::class, $builds);
        $this->assertTrue($builds->has('NOX'));
        $this->assertTrue($builds->has('Teuchos'));
        $this->assertInstanceOf(Build::class, $builds->get('NOX'));
        $this->assertInstanceOf(Build::class, $builds->get('Teuchos'));
    }

    public function testTestUseCaseCreatesTestPassed()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);

        /** @var UseCase $sut */
        $sut
            ->createSite(['Name' => 'Site.Name'])
            ->createTestPassed('some.test.name');

        /** @var TestingHandler $handler */
        $handler = $sut->build();
        /** @var BuildCollection $builds */
        $builds = $handler->GetBuildCollection();
        /** @var Build $build */
        $build = $builds->current();
        /** @var Illuminate\Support\Collection $tests */
        $tests = $build->GetTestCollection();
        /** @var Test $test */
        $test = $tests->get('some.test.name');

        $this->assertEquals(TestUseCase::PASSED, $test->status);
        $this->assertEquals('Completed', $test->details);
    }

    public function testTestUseCaseCreatesTestFailed()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);

        /** @var UseCase $sut */
        $sut
            ->createSite(['Name' => 'Site.Name'])
            ->createTestFailed('some.test.name');

        /** @var TestingHandler $handler */
        $handler = $sut->build();
        /** @var BuildCollection $builds */
        $builds = $handler->GetBuildCollection();
        /** @var Build $build */
        $build = $builds->current();
        /** @var Illuminate\Support\Collection $tests */
        $tests = $build->GetTestCollection();
        /** @var Test $test */
        $test = $tests->get('some.test.name');

        $this->assertEquals(TestUseCase::FAILED, $test->status);
        $this->assertEquals('Completed (Failed)', $test->details);
    }

    public function testTestUseCaseCreatesTestTimeout()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);

        /** @var UseCase $sut */
        $sut
            ->createSite(['Name' => 'Site.Name'])
            ->createTestTimedout('some.test.name');

        /** @var TestingHandler $handler */
        $handler = $sut->build();
        /** @var BuildCollection $builds */
        $builds = $handler->GetBuildCollection();
        /** @var Build $build */
        $build = $builds->current();
        /** @var Illuminate\Support\Collection $tests */
        $tests = $build->GetTestCollection();
        /** @var Test $test */
        $test = $tests->get('some.test.name');

        $this->assertEquals(TestUseCase::FAILED, $test->status);
        $this->assertEquals('Completed (Timeout)', $test->details);
    }

    public function testTestUseCaseCreatesTestNotRun()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);

        /** @var UseCase $sut */
        $sut
            ->createSite(['Name' => 'Site.Name'])
            ->createTestNotRun('some.test.name');

        /** @var TestingHandler $handler */
        $handler = $sut->build();
        /** @var BuildCollection $builds */
        $builds = $handler->GetBuildCollection();
        /** @var Build $build */
        $build = $builds->current();
        /** @var Illuminate\Support\Collection $tests */
        $tests = $build->GetTestCollection();
        /** @var Test $test */
        $test = $tests->get('some.test.name');

        $this->assertEquals(TestUseCase::NOTRUN, $test->status);
        $this->assertEquals('', $test->details);
    }

    public function testTestUseCaseCreatesMultisubprojectTestXMLFile()
    {
        $sut = UseCase::createBuilder($this, UseCase::TEST);

        /** @var TestUseCase $sut */
        $sut
            ->createSite([
                'Name' => 'Site.Name',
                'BuildName' => 'SomeOS-SomeBuild',
                'BuildStamp' => '123456789-2018-Nightly'
            ])
            ->setStartTime(1235383453)
            ->setEndTime(1235383473)
            ->createSubproject('MyExperimentalFeature')
            ->createSubproject('MyProductionCode', ['MyProductionCode', 'AnotherLabel'])
            ->createSubproject(
                'MyThirdPartyDependency',
                ['MyThirdPartyDependency1', 'MyThirdPartyDependency2']
            )
            ->createSubProject('EmptySubproject')
            ->createTestPassed('production', ['MyProductionCode'])
            ->createTestFailed('experimentalFail1', ['MyExperimentalFeature'])
            ->createTestNotRun('thirdparty', ['MyThirdPartyDependency1'])
            ->createTestTimedout('test4', ['MyExperimentalFeature']);

        /** @var TestingHandler $handler */
        $handler = $sut->build();

        /** @var BuildCollection $builds */
        $builds = $handler->GetBuildCollection();
        $this->assertCount(4, $builds);

        /** @var Build $build */
        $build = $builds->get('MyExperimentalFeature');
        $tests = $build->GetTestCollection();
        $this->assertCount(2, $tests);
        $this->assertTrue($tests->has('experimentalFail1'));
        $this->assertTrue($tests->has('test4'));

        /** @var Test $test */
        $test = $tests->get('experimentalFail1');
        $this->assertEquals(TestUseCase::FAILED, $test->status);

        $test = $tests->get('test4');
        $this->assertEquals(TestUseCase::FAILED, $test->status);

        $build = $builds->get('MyProductionCode');
        $tests = $build->GetTestCollection();
        $test = $tests->get('production');
        $this->assertEquals(TestUseCase::PASSED, $test->status);

        $build = $builds->get('MyThirdPartyDependency');
        $tests = $build->GetTestCollection();
        $test = $tests->get('thirdparty');
        $this->assertEquals(TestUseCase::NOTRUN, $test->status);
    }

    public function testUseCaseSetsPropertiesByTestName()
    {
        /** @var TestUseCase $sut */
        $sut = UseCase::createBuilder($this, UseCase::TEST);
        $sut
            ->createSite([
                'Name' => 'elysium',
                'BuildName' => 'test_timing',
                'BuildStamp' => '20180125-1723-Experimental',
            ])
            ->setStartTime(1516900999)
            ->setEndTime(1516901001)
            ->createTestPassed('nap');

        $sut->setTestProperties('nap', ['Execution Time' => '2.00447']);
        /** @var TestingHandler $handler */
        $handler = $sut->build();

        $builds = $handler->GetBuildCollection();
        $build = $builds->current();
        $tests = $build->GetTestCollection();
        $test = $tests->get('nap');

        $this->assertEquals(2.00447, $test->time);

        // Test again to ensure that the previous value is replaced with the new value
        $sut->setTestProperties('nap', ['Execution Time' => '9.123456']);
        /** @var TestingHandler $handler */
        $handler = $sut->build();

        $builds = $handler->GetBuildCollection();
        $build = $builds->current();
        $tests = $build->GetTestCollection();
        $test = $tests->get('nap');

        $this->assertEquals(9.123456, $test->time);
    }
}
