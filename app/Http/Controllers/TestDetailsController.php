<?php

namespace App\Http\Controllers;

use App\Models\Test;
use CDash\Controller\Api\TestDetails as LegacyTestDetailsController;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TestDetailsController extends AbstractBuildController
{
    public function details(int $id): View
    {
        $test = Test::find($id);
        $this->setBuildById($test->buildid ?? -1);

        $previousBuild = \App\Models\Build::find($this->build->GetPreviousBuildId());
        $latestBuild = \App\Models\Build::find($this->build->GetCurrentBuildId());
        $nextBuild = \App\Models\Build::find($this->build->GetNextBuildId());

        $previousTest = $previousBuild?->tests()->firstWhere('testname', $test?->testname);
        $latestTest = $latestBuild?->tests()->firstWhere('testname', $test?->testname);
        $nextTest = $nextBuild?->tests()->firstWhere('testname', $test?->testname);

        return $this->vue('test-details', 'Test Results')
            ->with('previousUrl', $previousTest?->GetUrlForSelf())
            ->with('latestUrl', $latestTest?->GetUrlForSelf())
            ->with('nextUrl', $nextTest?->GetUrlForSelf());
    }

    public function apiTestDetails(): JsonResponse|StreamedResponse
    {
        $buildtestid = request()->input('buildtestid');
        if (!is_numeric($buildtestid)) {
            abort(400, 'A valid test was not specified.');
        }

        $buildtest = Test::where('id', '=', $buildtestid)->first();
        if ($buildtest === null) {
            // Create a dummy project object to prevent information leakage between different error cases
            $project = new Project();
            $project->Id = -1;
        } else {
            $build = new Build();
            $build->Id = $buildtest->buildid;
            $build->FillFromId($build->Id);
            $project = $build->GetProject();
        }

        Gate::authorize('view-project', $project);

        // This case should never occur since it should always be caught by the Gate::authorize check above.
        // This is only here to satisfy PHPStan...
        if ($buildtest === null) {
            abort(500);
        }

        $controller = new LegacyTestDetailsController(Database::getInstance(), $buildtest);
        return $controller->getResponse();
    }
}
