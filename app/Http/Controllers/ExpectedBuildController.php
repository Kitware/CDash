<?php
namespace App\Http\Controllers;

use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ExpectedBuildController extends AbstractProjectController
{
    public function apiResponse(Request $request): JsonResponse
    {
        $required_params = ['siteid', 'groupid', 'name', 'type'];
        foreach ($required_params as $param) {
            if (!$request->has($param)) {
                abort(400, "$param not specified.");
            }
        }
        $siteid = (int) $request->input('siteid');
        $buildgroupid = (int) $request->input('groupid');
        $buildname = htmlspecialchars($request->input('name'));
        $buildtype = htmlspecialchars($request->input('type'));

        // Make sure the user has access to this project.
        $buildgroup = new BuildGroup();
        if (!$buildgroup->SetId($buildgroupid)) {
            abort(404, "Could not find project for buildgroup #$buildgroupid");
        }

        $this->setProjectById($buildgroup->GetProjectId());

        // Make sure the user is an admin before proceeding with non-read-only methods.
        if (!$request->isMethod('GET') && !Gate::allows('edit-project', $this->project)) {
            abort(403, 'You do not have permission to access this page');
        }

        // Route based on what type of request this is.
        switch ($request->method()) {
            case 'DELETE':
                self::rest_delete($siteid, $buildgroupid, $buildname, $buildtype);
                break;
            case 'GET':
                return self::rest_get($request, $siteid, $buildname, $buildtype, $this->project->Id);
            case 'POST':
                self::rest_post($request, $siteid, $buildgroupid, $buildname, $buildtype);
                break;
            default:
                abort(500, "Unhandled method: " . $request->method());
        }

        return response()->json();
    }

    private static function rest_get(Request $request, int $siteid, string $buildname, string $buildtype, int $projectid): JsonResponse
    {
        $response = [];

        if (!$request->has('currenttime')) {
            abort(400, '"currenttime" not specified in request.');
        }
        $currenttime = (int) $request->input('currenttime');
        $currentUTCtime = gmdate(FMT_DATETIME, $currenttime);

        // Find the last time this expected build submitted.
        $lastBuildDate = DB::select('
            SELECT starttime
            FROM build
            WHERE
                siteid = ?
                AND type = ?
                AND name = ?
                AND projectid = ?
                AND starttime <= ?
            ORDER BY starttime DESC
            LIMIT 1
        ', [
            $siteid,
            $buildtype,
            $buildname,
            $projectid,
            $currentUTCtime,
        ])[0]->starttime ?? null;

        if ($lastBuildDate === null) {
            $response['lastSubmission'] = -1;
            return response()->json($response);
        }

        $gmtime = strtotime($lastBuildDate . ' UTC');
        $response['lastSubmission'] = date('M j, Y ', $gmtime);
        $response['lastSubmissionDate'] = date('Y-m-d', $gmtime);
        $response['daysSinceLastBuild'] = round(($currenttime - strtotime($lastBuildDate)) / (3600 * 24));

        return response()->json(cast_data_for_JSON($response));
    }

    private static function rest_post(Request $request, int $siteid, int $buildgroupid, string $buildname, string $buildtype): void
    {
        if (!$request->has('newgroupid')) {
            abort(400, 'newgroupid not specified.');
        }

        $newgroupid = htmlspecialchars($request->input('newgroupid'));

        $rule = new BuildGroupRule();
        $rule->SiteId = $siteid;
        $rule->GroupId = $buildgroupid;
        $rule->BuildName = $buildname;
        $rule->BuildType = $buildtype;
        $rule->ChangeGroup($newgroupid);
    }

    private static function rest_delete(int $siteid, int $buildgroupid, string $buildname, string $buildtype): void
    {
        $rule = new BuildGroupRule();
        $rule->SiteId = $siteid;
        $rule->GroupId = $buildgroupid;
        $rule->BuildName = $buildname;
        $rule->BuildType = $buildtype;
        $rule->Delete();
    }
}
