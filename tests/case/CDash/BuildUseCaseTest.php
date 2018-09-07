<?php

use CDash\Model\Build;
use CDash\Test\CDashUseCaseTestCase;
use CDash\Test\UseCase\BuildUseCase;
use CDash\Test\UseCase\UseCase;

class BuildUseCaseTest extends CDashUseCaseTestCase
{
  public function testUseCaseCreateBuilderReturnsInstanceOfBuildUseCase()
  {
    $sut = UseCase::createBuilder($this, UseCase::BUILD);
    $this->assertInstanceOf(BuildUseCase::class, $sut);
  }

  public function testBuildUseCase()
  {
      $now = time();
      $hour_ago = $now - 60 * 60;
      $hour_and_half_ago = $hour_ago - 60 * 30;

      /** @var BuildUseCase $sut */
      $sut = UseCase::createBuilder($this, UseCase::BUILD)
          ->createSite([
              'Name' => 'Site.name',
              'BuildName' => 'CTestTest-Linux-c++-Subprojects',
          ])
          ->createSubproject('MyExperimentalFeature')
          ->createSubproject('MyProductionCode', ['MyProductionCode', 'AnotherLabel'])
          ->createSubproject(
              'MyThirdPartyDependency',
              ['MyThirdPartyDependency1', 'MyThirdPartyDependency2']
          )
          ->createSubproject('EmptySubproject')
          ->setStartTime($hour_and_half_ago)
          ->setEndTime($hour_ago)
          ->setBuildCommand('cmake --build . --config "Debug" -- -i')
          ->createBuildFailureError('MyThirdPartyDependency1')
          ->createBuildFailureError('MyThirdPartyDependency2')
          ->createBuildFailureWarning('MyExperimentalFeature')
          ->createBuildFailureWarning('MyProductionCode');

      /** @var ActionableBuildInterface $handler */
      $handler = $sut->build();
      $this->assertInstanceOf(BuildHandler::class, $handler);

      /** @var \CDash\Collection\BuildCollection $builds */
      $builds = $handler->GetBuildCollection();
      $this->assertCount(4, $builds);

      /** @var Build $third_party */
      $third_party = $builds->get('MyThirdPartyDependency');
      $this->assertInstanceOf(Build::class, $third_party);
      $this->assertCount(2, $third_party->Errors);

      /** @var Build $experimental */
      $experimental = $builds->get('MyExperimentalFeature');
      $this->assertInstanceOf(Build::class, $experimental);
      $this->assertCount(1, $experimental->Errors);

      /** @var Build $production */
      $production = $builds->get('MyProductionCode');
      $this->assertInstanceOf(Build::class, $production);
      $this->assertCount(1, $production->Errors);
  }
}

