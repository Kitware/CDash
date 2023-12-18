<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\AuthTokenUtil;
use App\Utils\PageTimer;
use App\Validators\Password;
use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildUpdate;
use CDash\Model\Project;
use App\Models\Site;
use CDash\Model\UserProject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

require_once 'include/cdashmail.php';

final class UserController extends AbstractController
{
    public function userPage(): View
    {
        return $this->view("admin.user");
    }

    public function userPageContent(): JsonResponse
    {
        $config = Config::getInstance();
        $response = [];
        if (!Auth::check()) {
            $response['requirelogin'] = 1;
            // Special handling for the fact that this is where new users are sent
            // when they first create their account.
            if (@$_GET['note'] === 'register') {
                $response['registered'] = 1;
            } else {
                $response['registered'] = 0;
            }
            http_response_code(401);
            return response()->json($response);
        }

        $pageTimer = new PageTimer();

        /** @var User $user */
        $user = Auth::user();
        $userid = $user->id;

        $PDO = Database::getInstance()->getPdo();

        $response = begin_JSON_response();
        $response['title'] = 'My Profile';

        $response['user_name'] = $user->firstname;
        $response['user_is_admin'] = $user->admin;
        $response['show_monitor'] = config('queue.default') === 'database';

        if (boolval(config('cdash.user_create_projects'))) {
            $response['user_can_create_projects'] = 1;
        } else {
            $response['user_can_create_projects'] = 0;
        }

        // Go through the list of project the user is part of.
        $UserProject = new UserProject();
        $UserProject->UserId = $userid;
        $project_rows = $UserProject->GetProjects();
        $start = gmdate(FMT_DATETIME, strtotime(date('r')) - (3600 * 24));
        $Project = new Project();
        $projects_response = [];
        foreach ($project_rows as $project_row) {
            $Project->Id = $project_row['id'];
            $Project->Name = $project_row['name'];
            $project_response = [];
            $project_response['id'] = $Project->Id;
            $project_response['role'] = $project_row['role']; // 0 is normal user, 1 is maintainer, 2 is administrator
            $project_response['name'] = $Project->Name;
            $project_response['name_encoded'] = urlencode($Project->Name);
            $project_response['nbuilds'] = $Project->GetNumberOfBuilds();
            $project_response['average_builds'] = round($Project->GetBuildsDailyAverage(gmdate(FMT_DATETIME, time() - (3600 * 24 * 7)), gmdate(FMT_DATETIME)), 2);
            $project_response['success'] = $Project->GetNumberOfPassingBuilds($start, gmdate(FMT_DATETIME));
            $project_response['error'] = $Project->GetNumberOfErrorBuilds($start, gmdate(FMT_DATETIME));
            $project_response['warning'] = $Project->GetNumberOfWarningBuilds($start, gmdate(FMT_DATETIME));
            $projects_response[] = $project_response;
        }
        $response['projects'] = $projects_response;

        $response['authtokens'] = AuthTokenUtil::getTokensForUser($userid);
        $response['allow_full_access_tokens'] = config('cdash.allow_full_access_tokens') === true;
        $response['allow_submit_only_tokens'] = config('cdash.allow_submit_only_tokens') === true;

        // Find all the public projects that this user is not subscribed to.
        $stmt = $PDO->prepare(
            'SELECT name, id FROM project
            WHERE public = 1
            AND id NOT IN (SELECT projectid AS id FROM user2project WHERE userid = ?)
            ORDER BY name');
        pdo_execute($stmt, [$userid]);

        $publicprojects_response = [];
        while ($row = $stmt->fetch()) {
            $publicproject_response = [];
            $publicproject_response['id'] = $row['id'];
            $publicproject_response['name'] = $row['name'];
            $publicprojects_response[] = $publicproject_response;
        }
        $response['publicprojects'] = $publicprojects_response;

        //Go through the claimed sites
        $claimedsiteprojects = [];
        $siteidwheresql = '';
        $claimedsites = [];
        $stmt = $PDO->prepare('SELECT siteid FROM site2user WHERE userid = ?');
        pdo_execute($stmt, [$userid]);
        $query_params = [];
        while ($row = $stmt->fetch()) {
            try {
                $Site = Site::findOrFail($row['siteid']);
            } catch (ModelNotFoundException $e) {
                abort(500, 'Invalid relation between site2user and site tables.');
            }

            $site_response = [];
            $site_response['id'] = $Site->id;
            $site_response['name'] = $Site->name;
            $site_response['outoforder'] = $Site->outoforder;
            $claimedsites[] = $site_response;

            if (strlen($siteidwheresql) > 0) {
                $siteidwheresql .= ' OR ';
            }
            $siteidwheresql .= " siteid=? ";
            $query_params[] = $Site->id;
        }

        // Look for all the projects
        if (count($claimedsites) > 0) {
            $stmt = $PDO->prepare(
                "SELECT b.projectid FROM build b
                JOIN user2project u2p ON (b.projectid = u2p.projectid)
                WHERE ($siteidwheresql) AND u2p.userid = ? AND u2p.role > 0
                GROUP BY b.projectid");
            pdo_execute($stmt, array_merge($query_params, [$userid]));
            while ($row = $stmt->fetch()) {
                $Project = new Project();
                $Project->Id = intval($row['projectid']);
                $Project->Fill();

                $claimedproject = [];
                $claimedproject['id'] = $Project->Id;
                $claimedproject['name'] = $Project->Name;
                $claimedproject['nightlytime'] = $Project->NightlyTime;
                $claimedsiteprojects[] = $claimedproject;
            }
        }


        // List the claimed sites
        $claimedsites_response = [];
        foreach ($claimedsites as $site) {
            $claimedsite_response = [];
            $claimedsite_response['id'] = $site['id'];
            $claimedsite_response['name'] = $site['name'];
            $claimedsite_response['outoforder'] = $site['outoforder'];

            $siteid = intval($site['id']);

            $siteprojects_response = [];
            foreach ($claimedsiteprojects as $project) {
                $siteproject_response = [];

                $projectid = intval($project['id']);
                $projectname = $project['name'];
                $nightlytime = $project['nightlytime'];

                $siteproject_response['nightly'] =
                    $this->ReportLastBuild('Nightly', $projectid, $siteid, $projectname, $nightlytime, $PDO);
                $siteproject_response['continuous'] =
                    $this->ReportLastBuild('Continuous', $projectid, $siteid, $projectname, $nightlytime, $PDO);
                $siteproject_response['experimental'] =
                    $this->ReportLastBuild('Experimental', $projectid, $siteid, $projectname, $nightlytime, $PDO);
                $siteprojects_response[] = $siteproject_response;
            }
            $claimedsite_response['projects'] = $siteprojects_response;
            $claimedsites_response[] = $claimedsite_response;
        }
        $response['claimedsites'] = $claimedsites_response;

        // Use to build the site/project matrix
        $claimedsiteprojects_response = [];
        foreach ($claimedsiteprojects as $project) {
            $claimedsiteproject_response = [];
            $claimedsiteproject_response['id'] = $project['id'];
            $claimedsiteproject_response['name'] = $project['name'];
            $claimedsiteproject_response['name_encoded'] = urlencode($project['name']);
            $claimedsiteprojects_response[] = $claimedsiteproject_response;
        }
        $response['claimedsiteprojects'] = $claimedsiteprojects_response;

        // TODO: (williamjallen) This logic doesn't make sense.  Investigate further.
        if (@$_GET['note'] === 'subscribedtoproject') {
            $response['message'] = 'You have subscribed to a project.';
        } elseif (@$_GET['note'] === 'subscribedtoproject') {
            $response['message'] = 'You have been unsubscribed from a project.';
        }

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    /** Report statistics about the last build */
    private function ReportLastBuild(string $type, int $projectid, int $siteid, string $projectname, $nightlytime, \PDO $PDO): array
    {
        $response = [];
        $nightlytime = strtotime($nightlytime);

        // Find the last build
        $stmt = $PDO->prepare(
            'SELECT starttime, id FROM build
        WHERE siteid = :siteid AND projectid = :projectid AND type = :type
        ORDER BY submittime DESC LIMIT 1');
        $stmt->bindParam(':siteid', $siteid);
        $stmt->bindParam(':projectid', $projectid);
        $stmt->bindParam(':type', $type);
        pdo_execute($stmt);
        $row = $stmt->fetch();
        if ($row) {
            $buildid = $row['id'];

            // Express the date in terms of days (makes more sens)
            $buildtime = strtotime($row['starttime'] . ' UTC');
            $builddate = $buildtime;

            if (date(FMT_TIME, $buildtime) > date(FMT_TIME, $nightlytime)) {
                $builddate += 3600 * 24; //next day
            }

            if (date(FMT_TIME, $nightlytime) < '12:00:00') {
                $builddate -= 3600 * 24; // previous date
            }

            $date = date(FMT_DATE, $builddate);
            $days = ((time() - strtotime($date)) / (3600 * 24));

            if ($days < 1) {
                $day = 'today';
            } elseif ($days > 1 && $days < 2) {
                $day = 'yesterday';
            } else {
                $day = round($days) . ' days';
            }
            $response['date'] = $day;
            $response['datelink'] = 'index.php?project=' . urlencode($projectname) . '&date=' . $date;

            // Configure
            $BuildConfigure = new BuildConfigure();
            $BuildConfigure->BuildId = $buildid;
            $configure_row = $BuildConfigure->GetConfigureForBuild();
            if ($configure_row) {
                $response['configure'] = $configure_row['status'];
                if ($configure_row['status'] != 0) {
                    $response['configureclass'] = 'error';
                } else {
                    $response['configureclass'] = 'normal';
                }
            } else {
                $response['configure'] = '-';
                $response['configureclass'] = 'normal';
            }

            // Update
            $nupdates = 0;
            $updateclass = 'normal';
            $BuildUpdate = new BuildUpdate();
            $BuildUpdate->BuildId = $buildid;
            $update_row = $BuildUpdate->GetUpdateForBuild();
            if ($update_row) {
                $nupdates = $update_row['nfiles'];
                if ($nupdates < 0) {
                    $nupdates = 0;
                }
                if ($update_row['warnings'] > 0) {
                    $updateclass = 'error';
                }
            }
            $response['update'] = $nupdates;
            $response['updateclass'] = $updateclass;

            // Find the number of errors and warnings
            $Build = new Build();
            $Build->Id = $buildid;
            $nerrors = $Build->GetNumberOfErrors();
            $response['error'] = $nerrors;
            $nwarnings = $Build->GetNumberOfWarnings();
            $response['warning'] = $nwarnings;

            // Set the color
            if ($nerrors > 0) {
                $response['errorclass'] = 'error';
            } elseif ($nwarnings > 0) {
                $response['errorclass'] = 'warning';
            } else {
                $response['errorclass'] = 'normal';
            }

            // Find the test
            $nnotrun = $Build->GetNumberOfNotRunTests();
            $nfail = $Build->GetNumberOfFailedTests();

            // Display the failing tests then the not run
            if ($nfail > 0) {
                $response['testfail'] = $nfail;
                $response['testfailclass'] = 'error';
            } elseif ($nnotrun > 0) {
                $response['testfail'] = $nnotrun;
                $response['testfailclass'] = 'warning';
            } else {
                $response['testfail'] = '0';
                $response['testfailclass'] = 'normal';
            }
            $response['NA'] = '0';
        } else {
            $response['NA'] = '1';
        }

        return $response;
    }

    public function edit(): View
    {
        $userid = Auth::id();
        // TODO: (williamjallen) Switch this to the new User model...
        $user = new \CDash\Model\User();
        $user->Id = $userid;
        $user->Fill();

        $error_msg = '';
        $other_msg = '';

        if (isset($_POST['updateprofile'])) {
            $email = $_POST['email'] ?? '';
            // TODO: (williamjallen) validate email addresses using proper email validation regex
            if (strlen($email) < 3 || !str_contains($email, '@')) {
                $error_msg = 'Email should be a valid address.';
            } else {
                $user->Email = $email;
                $user->Institution = $_POST['institution'] ?? '';
                $user->LastName = $_POST['lname'] ?? '';
                $user->FirstName = $_POST['fname'] ?? '';
                if ($user->Save()) {
                    $other_msg = 'Your profile has been updated.';
                } else {
                    $error_msg = 'Cannot update profile.';
                }
            }
        }

        // Update the password
        if (isset($_POST['updatepassword'])) {
            $oldpasswd = $_POST['oldpasswd'] ?? '';
            $passwd = $_POST['passwd'] ?? '';
            $passwd2 = $_POST['passwd2'] ?? '';

            $password_is_good = true;

            if (!password_verify($oldpasswd, $user->Password) && md5($oldpasswd) !== $user->Password) {
                $password_is_good = false;
                $error_msg = 'Your old password is incorrect.';
            }

            if ($password_is_good && $passwd !== $passwd2) {
                $password_is_good = false;
                $error_msg = 'Passwords do not match.';
            }

            $minimum_length = config('cdash.password.min');
            if ($password_is_good && strlen($passwd) < $minimum_length) {
                $password_is_good = false;
                $error_msg = "Password must be at least $minimum_length characters.";
            }

            if ($password_is_good && config('cdash.password.expires') > 0) {
                $query = 'SELECT password FROM password WHERE userid = ?';
                $query_params = [(int) $userid];
                $unique_count = (int) config('cdash.password.unique');
                if ($unique_count > 0) {
                    $query .= ' ORDER BY date DESC LIMIT ?';
                    $query_params[] = $unique_count;
                }
                $query_result = DB::select($query, $query_params);
                foreach ($query_result as $row) {
                    if (password_verify($passwd, $row->password)) {
                        $password_is_good = false;
                        $error_msg = 'You have recently used this password.  Please select a new one.';
                        break;
                    }
                }
            }

            if ($password_is_good) {
                $password_validator = new Password;
                $complexity_count = config('cdash.password.count');
                $complexity = $password_validator->computeComplexity($passwd, $complexity_count);
                $minimum_complexity = config('cdash.password.complexity');
                if ($complexity < $minimum_complexity) {
                    $password_is_good = false;
                    if ($complexity_count > 1) {
                        $error_msg = "Your password must contain at least $complexity_count characters from $minimum_complexity of the following types: uppercase, lowercase, numbers, and symbols.";
                    } else {
                        $error_msg = "Your password must contain at least $minimum_complexity of the following: uppercase, lowercase, numbers, and symbols.";
                    }
                }
            }

            if ($password_is_good) {
                $user->Password = password_hash($passwd, PASSWORD_DEFAULT);
                if ($user->Save()) {
                    $other_msg = 'Your password has been updated.';
                    if (isset($_SESSION['cdash']['redirect'])) {
                        unset($_SESSION['cdash']['redirect']);
                        request()->session()->remove('cdash.redirect');
                    }
                } else {
                    $error_msg = 'Cannot update password.';
                }
            }
        }

        if (request('password_expired')) {
            $error_msg = 'Password has expired';
        }

        // Update the credentials
        if (isset($_POST['updatecredentials'])) {
            $credentials = $_POST['credentials'] ?? [];
            $UserProject = new UserProject();
            $UserProject->ProjectId = 0;
            $UserProject->UserId = $userid;
            $credentials[] = $user->Email;
            $UserProject->UpdateCredentials($credentials);
        }

        // List the credentials
        $credentials_query = DB::select('
            SELECT credential
            FROM user2repository
            WHERE
                userid = ?
              AND projectid = 0
        ', [(int) $userid]);

        $credentials = [];

        foreach ($credentials_query as $credential) {
            if ($credential->credential === $user->Email) {
                // Move the email credential (which cannot be changed) to the top of the array
                array_unshift($credentials, $credential->credential);
            } else {
                $credentials[] = $credential->credential;
            }
        }


        if (($_GET['reason'] ?? '') === 'expired') {
            $error_msg = 'Your password has expired. Please set a new one.';
        }

        return view('auth.profile')
            ->with('user', $user)
            ->with('error', $error_msg)
            ->with('message', $other_msg)
            ->with('credentials', $credentials);
    }

    public function recoverPassword(): View
    {
        $config = Config::getInstance();

        $message = '';
        $warning = '';
        if (isset($_POST['recover'])) {
            $email = $_POST['email'];
            $user = new \CDash\Model\User();
            $userid = User::firstWhere('email', $email)?->id;
            if ($userid === null) {
                // Don't reveal whether or not this is a valid account.
                $message = 'A confirmation message has been sent to your inbox.';
            } else {
                // Create a new password
                $password = generate_password(10);

                $currentURI = $config->getBaseUrl();
                $url = $currentURI . '/user';

                $text = "Hello,\n\n You have asked to recover your password for CDash.\n\n";
                $text .= 'Your new password is: ' . $password . "\n";
                $text .= 'Please go to this page to login: ';
                $text .= "$url\n";
                $text .= "\n\nGenerated by CDash";

                if (cdashmail("$email", 'CDash password recovery', $text)) {
                    // If we can send the email we update the database
                    $user->Id = $userid;
                    $user->Fill();
                    $user->Password = password_hash($password, PASSWORD_DEFAULT);
                    $user->Save();
                    $message = 'A confirmation message has been sent to your inbox.';
                } else {
                    $warning = 'Cannot send recovery email';
                }
            }
        }

        return $this->view('user.recover-password')
            ->with('message', $message)
            ->with('warning', $warning);
    }
}
