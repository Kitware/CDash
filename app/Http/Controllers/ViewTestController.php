<?php

namespace App\Http\Controllers;

use CDash\Controller\Api\ViewTest;
use CDash\Database;
use CDash\Model\Build;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ViewTestController extends AbstractBuildController
{
    public function viewTest(): View
    {
        $this->setBuildById(request()->integer('buildid'));
        return $this->view('test.view-test', 'Tests');
    }

    /**
     * View tests of a particular build.
     *
     * GET /viewTest.php
     * Required Params:
     * buildid=[integer] The ID of the build
     *
     * Optional Params:
     *
     * date=[YYYY-mm-dd]
     * tests=[array of test names]
     *   If tests is passed the following parameters apply:
     *       Required:
     *         projectid=[integer]
     *         groupid=[integer]
     *       Optional:
     *         previous_builds=[comma separated list of build ids]
     *         time_begin=[SQL compliant comparable to timestamp]
     *         time_end=[SQL compliant comparable to timestamp]
     * onlypassed=[presence]
     * onlyfailed=[presence]
     * onlytimestatus=[presence]
     * onlynotrun=[presence]
     * onlydelta=[presence]
     * filterstring
     * export=[presence]
     *
     * TODO: figure out the return type for this function...
     **/
    public function fetchPageContent(): JsonResponse|StreamedResponse
    {
        $db = Database::getInstance();
        $controller = new ViewTest($db, self::get_request_build());
        $response = $controller->getResponse();
        if ($controller->JSONEncodeResponse) {
            return response()->json(cast_data_for_JSON($response));
        }
        $headers = [
            'Content-Type' => 'text/csv',
        ];
        return response()->streamDownload(function () use ($response): void {
            echo $response;
        }, 'test-export.csv', $headers);
    }

    /**
     * Returns a build based on the id extracted from the request and returns it if the user has
     * necessary access to the project
     *
     * @param bool $required
     */
    private static function get_request_build($required = true): ?Build
    {
        $id = self::get_request_build_id($required);
        if (null === $id) {
            return null;
        }
        $build = new Build();
        $build->Id = $id;

        if ($required && !$build->Exists()) {
            abort(400, 'This build does not exist. Maybe it has been deleted.');
        }

        if ($id) {
            $build->FillFromId($id);
        }

        return can_access_project($build->ProjectId) ? $build : null;
    }

    /**
     * Pulls the buildid from the request
     *
     * @param bool $required
     */
    private static function get_request_build_id($required = true): ?int
    {
        $buildid = self::get_int_param('buildid', $required);
        return $buildid;
    }

    private static function get_int_param($name, $required = true): ?int
    {
        $value = get_param($name, $required);
        if (null === $value) {
            return null;
        }

        if ($required && !is_numeric($value)) {
            abort(400, "Valid $name required");
        }
        return (int) $value;
    }
}
