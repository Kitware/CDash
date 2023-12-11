<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Utils\PageTimer;
use CDash\Config;
use CDash\Model\Project;
use CDash\Model\Repository;
use CDash\Model\UserProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class ProjectController extends AbstractProjectController
{
    public function apiCreateProject(): JsonResponse
    {
        $pageTimer = new PageTimer();
        $config = Config::getInstance();

        if (isset($_GET['projectid'])) {
            // We're editing a project if a projectid was specified
            $this->setProjectById(intval($_GET['projectid']));
        } else {
            // We're going to create a new project
            $this->project = new Project();
        }

        /** @var \App\Models\User $User */
        $User = Auth::user();

        // Check if the user has the necessary permissions.
        if ($this->project->Exists()) {
            // Can they edit this project?
            Gate::authorize('edit-project', $this->project);
        } else {
            // Can they create a new project?
            Gate::authorize('create-project');
        }

        $response = begin_JSON_response();
        if ($this->project->Exists()) {
            get_dashboard_JSON($this->project->GetName(), null, $response);
        }
        $response['hidenav'] = 1;

        $nRepositories = 0;
        $repositories_response = [];

        if ($this->project->Exists()) {
            $response['title'] = 'Edit Project';
            $response['edit'] = 1;
        } else {
            $response['title'] = 'New Project';
            $response['edit'] = 0;
            $response['noproject'] = 1;
        }

        // List the available projects
        $callback = function ($project) {
            if ($project['id'] === $this->project->Id) {
                $project['selected'] = 1;
            }
            return $project;
        };

        $response['availableprojects'] = array_map($callback, UserProject::GetProjectsForUser($User));

        $project_response = [];
        if ($this->project->Exists()) {
            $project_response = $this->project->ConvertToJSON();

            // Get the spam list
            $spambuilds = $this->project->GetBlockedBuilds();
            $blocked_builds = [];
            foreach ($spambuilds as $spambuild) {
                $blocked_builds[] = $spambuild;
            }
            $project_response['blockedbuilds'] = $blocked_builds;

            $repositories = $this->project->GetRepositories();
            foreach ($repositories as $repository) {
                $repository_response = [];
                $repository_response['url'] = $repository['url'];
                $repository_response['username'] = $repository['username'];
                $repository_response['password'] = $repository['password'];
                $repository_response['branch'] = $repository['branch'];
                $repositories_response[] = $repository_response;
                $nRepositories++;
            }
        } else {
            // Initialize some variables for project creation.
            $project_response['AuthenticateSubmissions'] = 0;
            $project_response['Public'] = Project::ACCESS_PRIVATE;
            $project_response['AutoremoveMaxBuilds'] = 500;
            $project_response['AutoremoveTimeframe'] = 60;
            $project_response['CoverageThreshold'] = 70;
            $project_response['EmailBrokenSubmission'] = 1;
            $project_response['EmailMaxChars'] = 255;
            $project_response['EmailMaxItems'] = 5;
            $project_response['NightlyTime'] = '01:00:00 UTC';
            $project_response['ShowCoverageCode'] = 1;
            $project_response['TestTimeMaxStatus'] = 3;
            $project_response['TestTimeStd'] = 4.0;
            $project_response['TestTimeStdThreshold'] = 1.0;
            if (!boolval(config('cdash.user_create_projects')) || $User->admin) {
                $project_response['UploadQuota'] = 1;
            }
            $project_response['WarningsFilter'] = "";
            $project_response['ErrorsFilter'] = "";
            $project_response['ViewSubProjectsLink'] = 1;
        }

        // Make sure we have at least one repository.
        if ($nRepositories == 0) {
            $repository_response = [];
            $repository_response['id'] = $nRepositories;
            $repository_response['url'] = '';
            $repository_response['branch'] = '';
            $repository_response['username'] = '';
            $repository_response['password'] = '';
            $repositories_response[] = $repository_response;
        }
        $project_response['repositories'] = $repositories_response;
        $response['project'] = $project_response;

        // Add the different types of Version Control System (VCS) viewers.
        if (strlen($this->project->CvsViewerType ?? '') === 0) {
            $this->project->CvsViewerType = 'github';
        }

        $viewers = Repository::getViewers();
        $callback = function ($key) use ($viewers, &$response) {
            $v = ['description' => $key, 'value' => $viewers[$key]];
            if ($this->project->CvsViewerType === $v['value']) {
                $response['selectedViewer'] = $v;
            }
            return $v;
        };

        $response['vcsviewers'] = array_map($callback, array_keys($viewers));

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function ajaxDailyUpdatesCurl(): void
    {
        require_once 'include/dailyupdates.php';

        addDailyChanges(intval($_GET['projectid']));
    }
}
