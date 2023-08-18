<?php
namespace App\Http\Controllers;

use CDash\Database;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

require_once 'include/api_common.php';

final class ViewTestController extends AbstractController
{
    public function viewTest(): View
    {
        return $this->view("test.view-test");
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
        $controller = new \CDash\Controller\Api\ViewTest($db, get_request_build());
        $response = $controller->getResponse();
        if ($controller->JSONEncodeResponse) {
            return response()->json(cast_data_for_JSON($response));
        } else {
            $headers = [
                'Content-Type' => 'text/csv',
            ];
            return response()->streamDownload(function () use ($response) {
                echo $response;
            }, 'test-export.csv', $headers);
        }
    }
}
