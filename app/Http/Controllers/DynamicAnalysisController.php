<?php
namespace App\Http\Controllers;

use App\Utils\PageTimer;
use App\Utils\TestingDay;
use CDash\Model\DynamicAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DynamicAnalysisController extends AbstractBuildController
{
    public function viewDynamicAnalysis(int $buildid): View
    {
        $this->setBuildById($buildid);
        return $this->view('dynamicanalysis.dynamic-analysis');
    }

    public function viewDynamicAnalysisFile(): Response
    {
        return response()->angular_view('viewDynamicAnalysisFile');
    }

    public function apiViewDynamicAnalysis(): JsonResponse
    {
        $pageTimer = new PageTimer();

        if (!isset($_GET['buildid']) || !is_numeric($_GET['buildid'])) {
            abort(400, 'Invalid buildid!');
        }
        $this->setBuildById((int) $_GET['buildid']);

        // lookup table to make the reported defect types more understandable.
        // feel free to expand as necessary.
        $defect_nice_names = [
            'FIM' => 'Freeing Invalid Memory',
            'IPR' => 'Invalid Pointer Read',
            'IPW' => 'Invalid Pointer Write',
        ];

        $date = TestingDay::get($this->project, $this->build->StartTime);

        $response = begin_JSON_response();
        get_dashboard_JSON($this->project->Name, $date, $response);
        $response['title'] = "{$this->project->Name} - Dynamic Analysis";

        $menu = [];
        if ($this->build->GetParentId() > 0) {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&parentid={$this->build->GetParentId()}";
        } else {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&date=$date";
        }

        $previousbuildid = self::get_previous_buildid_dynamicanalysis($this->build->ProjectId, $this->build->SiteId, $this->build->Type, $this->build->Name, $this->build->StartTime);
        if ($previousbuildid > 0) {
            $menu['previous'] = "/build/$previousbuildid/dynamic_analysis";
        } else {
            $menu['previous'] = false;
        }

        $currentbuildid = self::get_last_buildid_dynamicanalysis($this->build->ProjectId, $this->build->SiteId, $this->build->Type, $this->build->Name);
        $menu['current'] = "/build/$currentbuildid/dynamic_analysis";

        $nextbuildid = self::get_next_buildid_dynamicanalysis($this->build->ProjectId, $this->build->SiteId, $this->build->Type, $this->build->Name, $this->build->StartTime);
        if ($nextbuildid > 0) {
            $menu['next'] = "/build/$nextbuildid/dynamic_analysis";
        } else {
            $menu['next'] = false;
        }
        $response['menu'] = $menu;

        // Build
        $build_response = [];
        $build_response['site'] = $this->build->GetSite()->name;
        $build_response['buildname'] = $this->build->Name;
        $build_response['buildid'] = $this->build->Id;
        $build_response['buildtime'] = $this->build->StartTime;
        $response['build'] = $build_response;

        // Dynamic Analysis
        $defect_types = [];
        $dynamic_analyses = [];

        // Process 50 rows at a time so we don't run out of memory.
        DB::table('dynamicanalysis')
            ->where('buildid', '=', $this->build->Id)
            ->orderBy('status', 'desc')
            ->chunk(50, function ($rows) use (&$dynamic_analyses, &$defect_types, $defect_nice_names) {
                foreach ($rows as $DA_row) {
                    $dynamic_analysis = [];
                    $dynamic_analysis['status'] = ucfirst($DA_row->status);
                    $dynamic_analysis['name'] = $DA_row->name;
                    $dynamic_analysis['id'] = $DA_row->id;

                    $dynid = $DA_row->id;
                    $defects_result = DB::select('SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid = ?', [$dynid]);
                    // Initialize defects array as zero for each type.
                    $num_types = count($defect_types);
                    if ($num_types > 0) {
                        // Work around a bug in older versions of PHP where the 2nd argument to
                        // array_fill must be greater than zero.
                        $defects = array_fill(0, count($defect_types), 0);
                    } else {
                        $defects = [];
                    }
                    foreach ($defects_result as $defects_row) {
                        // Figure out how many defects of each type were found for this test.
                        $defect_type = $defects_row->type;
                        if (array_key_exists($defect_type, $defect_nice_names)) {
                            $defect_type = $defect_nice_names[$defect_type];
                        }
                        if (!in_array($defect_type, $defect_types, true)) {
                            $defect_types[] = $defect_type;
                            $defects[] = 0;
                        }

                        $column = array_search($defect_type, $defect_types, true);
                        $defects[$column] = $defects_row->value;
                    }
                    $dynamic_analysis['defects'] = $defects;

                    if ($this->project->DisplayLabels) {
                        get_labels_JSON_from_query_results('
                            SELECT text
                            FROM label, label2dynamicanalysis
                            WHERE
                                label.id = label2dynamicanalysis.labelid
                                AND label2dynamicanalysis.dynamicanalysisid = ?
                            ORDER BY text ASC
                        ', [intval($dynid)], $dynamic_analysis);

                        if (array_key_exists('labels', $dynamic_analysis)) {
                            $dynamic_analysis['labels'] = implode(', ', $dynamic_analysis['labels']);
                        } else {
                            $dynamic_analysis['labels'] = '';
                        }
                    }

                    $dynamic_analyses[] = $dynamic_analysis;
                }
            });

        // Insert zero entries for types of defects that were not detected by a given test.
        $num_defect_types = count($defect_types);
        foreach ($dynamic_analyses as &$dynamic_analysis) {
            for ($i = 0; $i < $num_defect_types; $i++) {
                if (!array_key_exists($i, $dynamic_analysis['defects'])) {
                    $dynamic_analysis['defects'][$i] = 0;
                }
            }
        }

        $response['dynamicanalyses'] = $dynamic_analyses;

        // explicitly list the defect types encountered here
        // so we can dynamically generate the header row
        $types_response = [];
        foreach ($defect_types as $defect_type) {
            $type_response = [];
            $type_response['type'] = $defect_type;
            $types_response[] = $type_response;
        }
        $response['defecttypes'] = $types_response;

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function apiViewDynamicAnalysisFile(): JsonResponse
    {
        $pageTimer = new PageTimer();

        // Make sure a valid id was specified.
        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            abort(400, 'Not a valid id!');
        }
        $id = $_GET['id'];
        $DA = new DynamicAnalysis();
        $DA->Id = $id;
        if (!$DA->Fill()) {
            abort(400, 'Not a valid id!');
        }

        // Get the build associated with this analysis.
        $this->setBuildById($DA->BuildId);

        $date = TestingDay::get($this->project, $this->build->StartTime);
        $response = begin_JSON_response();
        get_dashboard_JSON($this->project->Name, $date, $response);
        $response['title'] = "{$this->project->Name} - Dynamic Analysis";

        // Build
        $build_response = [];
        $build_response['site'] = $this->build->GetSite()->name;
        $build_response['buildname'] = $this->build->Name;
        $build_response['buildid'] = $this->build->Id;
        $build_response['buildtime'] = $this->build->StartTime;
        $response['build'] = $build_response;

        // Menu
        $menu_response = [];
        $menu_response['back'] = "/build/{$this->build->Id}/dynamic_analysis";
        $previous_id = $DA->GetPreviousId($this->build);
        if ($previous_id > 0) {
            $menu_response['previous'] = "viewDynamicAnalysisFile.php?id=$previous_id";
        } else {
            $menu_response['previous'] = false;
        }
        $current_id = $DA->GetLastId($this->build);
        $menu_response['current'] = "viewDynamicAnalysisFile.php?id=$current_id";
        $next_id = $DA->GetNextId($this->build);
        if ($next_id > 0) {
            $menu_response['next'] = "viewDynamicAnalysisFile.php?id=$next_id";
        } else {
            $menu_response['next'] = false;
        }
        $response['menu'] = $menu_response;

        // dynamic analysis
        $DA_response = [];
        $DA_response['status'] = ucfirst($DA->Status);
        $DA_response['filename'] = $DA->Name;
        // Only display the first 1MB of the log (in case it's huge)
        $DA_response['log'] = substr($DA->Log, 0, 1024 * 1024);
        $href = "testSummary.php?project={$this->project->Id}&name=$DA->Name&date=$date";
        $DA_response['href'] = $href;
        $response['dynamicanalysis'] = $DA_response;

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    /**
     * Get the previous build id dynamicanalysis
     */
    private static function get_previous_buildid_dynamicanalysis(int $projectid, int $siteid, string $buildtype, string $buildname, string $starttime): int
    {
        $previousbuild = DB::select('
                             SELECT build.id
                             FROM build, dynamicanalysis
                             WHERE
                                 build.siteid=?
                                 AND build.type=?
                                 AND build.name=?
                                 AND build.projectid=?
                                 AND build.starttime<?
                                 AND dynamicanalysis.buildid=build.id
                             ORDER BY build.starttime DESC
                             LIMIT 1
                         ', [$siteid, $buildtype, $buildname, $projectid, $starttime])[0] ?? [];

        return $previousbuild->id ?? 0;
    }

    /**
     * Get the next build id dynamicanalysis
     */
    private static function get_next_buildid_dynamicanalysis(int $projectid, int $siteid, string $buildtype, string $buildname, string $starttime): int
    {
        $nextbuild = DB::select('
                         SELECT build.id
                         FROM build, dynamicanalysis
                         WHERE
                             build.siteid=?
                             AND build.type=?
                             AND build.name=?
                             AND build.projectid=?
                             AND build.starttime>?
                             AND dynamicanalysis.buildid=build.id
                         ORDER BY build.starttime ASC
                         LIMIT 1
                     ', [$siteid, $buildtype, $buildname, $projectid, $starttime])[0] ?? [];

        return $nextbuild->id ?? 0;
    }

    /**
     * Get the last build id dynamicanalysis
     */
    private static function get_last_buildid_dynamicanalysis(int $projectid, int $siteid, string $buildtype, string $buildname): int
    {
        $nextbuild = DB::select('
                         SELECT build.id
                         FROM build, dynamicanalysis
                         WHERE
                             build.siteid=?
                             AND build.type=?
                             AND build.name=?
                             AND build.projectid=?
                             AND dynamicanalysis.buildid=build.id
                         ORDER BY build.starttime DESC
                         LIMIT 1
                     ', [$siteid, $buildtype, $buildname, $projectid])[0] ?? [];

        return $nextbuild->id ?? 0;
    }
}
