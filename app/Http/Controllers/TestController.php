<?php

namespace App\Http\Controllers;

use App\Models\Test;
use CDash\Controller\Api\QueryTests as LegacyQueryTestsController;
use CDash\Controller\Api\TestGraph as LegacyTestGraphController;
use CDash\Controller\Api\TestOverview as LegacyTestOverviewController;
use CDash\Database;
use CDash\Model\Build;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class TestController extends AbstractProjectController
{
    public function queryTests(): View
    {
        $this->setProjectByName(request()->string('project'));
        return $this->angular_view('queryTests', 'Query Tests');
    }

    public function apiQueryTests(): JsonResponse
    {
        if (!request()->has('project')) {
            return response()->json(['error' => 'Valid project required']);
        }

        $this->setProjectByName(request()->string('project'));

        $controller = new LegacyQueryTestsController(Database::getInstance(), $this->project);
        return response()->json(cast_data_for_JSON($controller->getResponse()));
    }

    public function testOverview(): View
    {
        $this->setProjectByName(request()->input('project'));
        return $this->angular_view('testOverview', 'Test Overview');
    }

    public function apiTestOverview(): JsonResponse
    {
        if (!request()->has('project')) {
            return response()->json(['error' => 'Valid project required']);
        }

        $this->setProjectByName(request()->input('project'));

        $db = Database::getInstance();
        $controller = new LegacyTestOverviewController($db, $this->project);
        return response()->json(cast_data_for_JSON($controller->getResponse()));
    }

    public function apiTestGraph(): JsonResponse
    {
        if (!request()->has('buildid')) {
            abort(400, '"buildid" parameter is required.');
        }
        $buildid = (int) request()->input('buildid');
        $build = new Build();
        $build->FillFromId($buildid);
        Gate::authorize('view-project', $build->GetProject());

        $db = Database::getInstance();

        $testname = request()->input('testname');

        $buildtest = Test::where('buildid', '=', $buildid)
            ->where('testname', '=', $testname)
            ->first();
        if ($buildtest === null) {
            abort(404, 'test not found');
        }

        $controller = new LegacyTestGraphController($db, $buildtest);
        $response = $controller->getResponse();
        return response()->json(cast_data_for_JSON($response));
    }
}
