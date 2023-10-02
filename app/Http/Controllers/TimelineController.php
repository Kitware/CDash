<?php

namespace App\Http\Controllers;

use CDash\Database;
use Illuminate\Http\JsonResponse;
use CDash\Controller\Api\Timeline as LegacyTimelineController;

final class TimelineController extends AbstractProjectController
{
    public function apiTimeline(): JsonResponse
    {
        $this->setProjectByName(request()->input('project', ''));
        $controller = new LegacyTimelineController(Database::getInstance(), $this->project);
        return response()->json(cast_data_for_JSON($controller->getResponse()));
    }
}
