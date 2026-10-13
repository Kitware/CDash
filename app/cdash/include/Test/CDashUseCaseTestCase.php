<?php

namespace CDash\Test;

use App\Models\Label;
use App\Models\TestMeasurement;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;
use CDash\Model\BuildUpdate;
use CDash\Model\DynamicAnalysis;
use CDash\Model\DynamicAnalysisSummary;
use CDash\Model\Image;
use CDash\Test\UseCase\UseCase;
use PHPUnit\Framework\MockObject\MockObject;

class CDashUseCaseTestCase extends CDashTestCase
{
    /**
     * The classes which the submission handlers resolve from the service container.  Each of these
     * is replaced with a mock when a use case is set up.  Any class which the handlers resolve
     * via app() but which is missing from this list will be instantiated for real instead.
     */
    private const MOCKED_MODELS = [
        Build::class,
        BuildConfigure::class,
        BuildError::class,
        BuildFailure::class,
        BuildUpdate::class,
        DynamicAnalysis::class,
        DynamicAnalysisSummary::class,
        Image::class,
        Label::class,
        TestMeasurement::class,
    ];

    /**
     * The bindings are registered on the container which app() resolves from rather than on
     * $this->app, because some tests call createApplication() themselves, which replaces the global
     * container instance.  A fresh application is created for every test, so these bindings do not
     * need to be removed when the test finishes.
     */
    public function setUseCaseModelFactory(UseCase $useCase): void
    {
        $this->setDatabaseMocked();

        foreach (self::MOCKED_MODELS as $class_name) {
            app()->bind($class_name, fn () => $this->makeUseCaseModelMock($class_name, $useCase));
        }
    }

    /**
     * @param class-string $class_name
     */
    private function makeUseCaseModelMock(string $class_name, UseCase $useCase): MockObject
    {
        $methods = [];
        foreach (['Insert', 'Update', 'Save', 'GetCommitAuthors', 'GetMissingTests'] as $method) {
            if (method_exists($class_name, $method)) {
                $methods[] = $method;
            }
        }

        $model = $this->getMockBuilder($class_name)
            ->onlyMethods($methods)
            ->getMock();

        if (method_exists($class_name, 'Save')) {
            $model->expects($this->any())
                ->method('Save')
                ->willReturnCallback(function () use ($class_name, $model, $useCase) {
                    $model->Id = $useCase->getIdForClass($class_name);
                    if (isset($model->Errors)) {
                        foreach ($model->Errors as $error) {
                            $error->BuildId = $model->Id;
                        }
                    }
                    return $model->Id;
                });
        }

        if (method_exists($class_name, 'Insert')) {
            $model->expects($this->any())
                ->method('Insert')
                ->willReturnCallback(function () use ($class_name, $model, $useCase) {
                    // TODO: discuss
                    if (!property_exists($model, 'Id')) {
                        $model->Id = null;
                    }

                    if (!$model->Id) {
                        $model->Id = $useCase->getIdForClass($class_name);
                    }
                    return $model->Id;
                });
        }

        if (method_exists($class_name, 'GetCommitAuthors')) {
            $model->expects($this->any())
                ->method('GetCommitAuthors')
                ->willReturnCallback(function () use ($useCase, $model) {
                    /* @var Build|\PHPUnit\Framework\MockObject\MockObject $model */
                    return $useCase->getAuthors($model->SubProjectName);
                });
        }

        if (method_exists($class_name, 'GetMissingTests')) {
            $model->expects($this->any())
                ->method('GetMissingTests')
                ->willReturnCallback(function () use ($useCase) {
                    $missing = [];
                    if (isset($useCase->missingTests)) {
                        $missing = $useCase->missingTests;
                    }
                    return $missing;
                });
        }

        return $model;
    }
}
