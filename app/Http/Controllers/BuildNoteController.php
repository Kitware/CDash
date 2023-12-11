<?php

namespace App\Http\Controllers;

use App\Models\Build as EloquentBuild;
use App\Models\Note;
use App\Utils\PageTimer;
use App\Utils\TestingDay;
use CDash\Model\Build;
use Illuminate\Http\JsonResponse;

/**
 * NOTE: The "build note" functionality is distinct from the "user note" functionality.
 */
final class BuildNoteController extends AbstractBuildController
{
    public function apiViewNotes(): JsonResponse
    {
        $this->setBuildById(intval($_GET['buildid'] ?? 0));

        $pageTimer = new PageTimer();

        $response = begin_JSON_response();
        $response['title'] = "{$this->project->Name} - Build Notes";

        $date = TestingDay::get($this->project, $this->build->StartTime);

        get_dashboard_JSON_by_name($this->project->Name, $date, $response);

        // Menu
        $menu = [];
        if ($this->build->GetParentId() > 0) {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&parentid={$this->build->GetParentId()}";
        } else {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . '&date=' . $date;
        }

        $previous_buildid = $this->build->GetPreviousBuildId();
        $current_buildid = $this->build->GetCurrentBuildId();
        $next_buildid = $this->build->GetNextBuildId();

        if ($previous_buildid > 0) {
            $menu['previous'] = "/build/$previous_buildid/notes";
        } else {
            $menu['previous'] = false;
        }

        $menu['current'] = "/build/$current_buildid/notes";

        if ($next_buildid > 0) {
            $menu['next'] = "/build/$next_buildid/notes";
        } else {
            $menu['next'] = false;
        }

        $response['menu'] = $menu;

        // Build/site info.
        $site_name = $this->build->GetSite()->name;
        $response['build'] = Build::MarshalResponseArray($this->build, ['site' => $site_name]);

        // Notes for this build.
        $notes = EloquentBuild::findOrFail($this->build->Id)->notes()->get();
        $notes_response = [];
        /** @var Note $note */
        foreach ($notes as $note) {
            $notes_response[] = [
                'name' => $note->name,
                'text' => $note->text,
                'time' => $note->pivot->time,
            ];
        }
        $response['notes'] = $notes_response;

        $pageTimer->end($response);

        return response()->json(cast_data_for_JSON($response));
    }
}
