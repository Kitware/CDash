<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Banner;
use CDash\Model\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ManageBannerController extends AbstractController
{
    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from manageBanner.php and have been copied (almost) as-is.
     */
    public function manageBanner(): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = new Project;
        if (isset($_GET['projectid']) && (int) $_GET['projectid'] > 0) {
            $project->Id = (int) $_GET['projectid'];
            Gate::authorize('edit-project', $project);
        } elseif ($user->admin) {
            // We are able to set the global banner
            $project->Id = (int) ($_GET['projectid'] ?? 0);
        } else {
            // Deny access
            abort(403, 'You do not have permission to access this page');
        }

        $available_projects = [];

        // If user is admin then we can add a banner for all projects
        if ($user->admin) {
            $root_project = new Project();
            $root_project->Id = 0;
            $root_project->Name = 'All';
            $available_projects[] = $root_project;
        }

        $sql = 'SELECT id, name FROM project';
        $params = [];
        if (!$user->admin) {
            $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid=? AND role>0)";
            $params[] = intval(Auth::id());
        }

        $projects = DB::select($sql, $params);
        foreach ($projects as $project_array) {
            $p = new Project();
            $p->Id = (int) $project_array->id;
            $p->Name = $project_array->name;
            $available_projects[] = $p;
        }

        // If submit has been pressed
        if (isset($_POST['updateMessage'])) {
            $banner = Banner::updateOrCreate(['projectid' => $project->Id], ['text' => $_POST['message']]);
        } else {
            $banner = Banner::findOrNew($project->Id);
            if (!isset($banner->projectid)) {
                $banner->projectid = $project->Id;
            }
        }

        return view('admin.banner')
            ->with('project', $project)
            ->with('available_projects', $available_projects)
            ->with('banner', $banner);
    }
}
