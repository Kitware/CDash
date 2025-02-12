<?php

namespace App\Http\Controllers;

use App\Models\Project as EloquentProject;
use App\Models\User;
use CDash\Database;
use CDash\Model\Project;
use CDash\Model\UserProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

final class ManageProjectRolesController extends AbstractProjectController
{
    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from manageProjectRoles.php and have been copied (almost)
     *       as-is.  The GET and POST logic is intertwined here, and should be separated into two
     *       distinct functions eventually.
     */
    public function viewPage(): View|RedirectResponse
    {
        /** @var User $current_user */
        $current_user = Auth::user();

        // If the projectid is not set and there is only one project we go directly to the page
        if (!isset($_GET['projectid']) && EloquentProject::count() === 1) {
            $eloquent_project = EloquentProject::all()->firstOrFail();
        } else {
            $eloquent_project = EloquentProject::find((int) ($_GET['projectid'] ?? -1)) ?? new EloquentProject();
        }

        $projectid = $eloquent_project->id;

        Gate::authorize('update', $eloquent_project);

        $project = new Project();

        $xml = begin_XML_for_XSLT();
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Project Roles</menusubtitle>';

        // Form post
        @$adduser = $_POST['adduser'];
        @$removeuser = $_POST['removeuser'];

        $userid = $_POST['userid'] ?? null;
        if ($userid !== null) {
            $userid = (int) $userid;
        }

        $role = $_POST['role'] ?? null;
        if ($role !== null) {
            $role = (int) $role;
        }

        $emailtype = $_POST['emailtype'] ?? null;
        if ($emailtype !== null) {
            $emailtype = (int) $emailtype;
        }

        @$updateuser = $_POST['updateuser'];
        @$importUsers = $_POST['importUsers'];

        @$registerUser = $_POST['registerUser'];

        if (isset($_POST['sendEmailToSiteMaintainers'])) {
            $emailMaintainers = htmlspecialchars(pdo_real_escape_string($_POST['emailMaintainers']));
            if (strlen($emailMaintainers) < 50) {
                $xml .= '<error>The email should be more than 50 characters.</error>';
            } else {
                $email_to = [];
                $maintainerids = self::find_site_maintainers(intval($projectid));
                foreach ($maintainerids as $maintainerid) {
                    $email_to[] = User::findOrFail((int) $maintainerid)->email;
                }

                $projectname = get_project_name($projectid);
                if (count($email_to) !== 0) {
                    Mail::raw($emailMaintainers, function ($message) use ($email_to, $projectname) {
                        $message->subject('CDash - ' . $projectname . ' : To Site Maintainers')
                            ->to($email_to);
                    });
                } else {
                    $xml .= '<error>There are no site maintainers for this project.</error>';
                }
            }
        }

        // Register a user
        if ($registerUser) {
            @$email = $_POST['registeruseremail'];
            if ($email != null) {
                $email = htmlspecialchars(pdo_real_escape_string($email));
            }
            @$firstName = $_POST['registeruserfirstname'];
            if ($firstName != null) {
                $firstName = htmlspecialchars(pdo_real_escape_string($firstName));
            }
            @$lastName = $_POST['registeruserlastname'];
            if ($lastName != null) {
                $lastName = htmlspecialchars(pdo_real_escape_string($lastName));
            }

            if (strlen($email) < 3 || strlen($firstName) < 2 || strlen($lastName) < 2) {
                $xml .= '<error>Email, first name and last name should be filled out.</error>';
            } else {
                // Call the register_user function
                $xml .= $this->register_user($projectid, $email, $firstName, $lastName);
            }
        }

        // Add a user
        if ($adduser) {
            $UserProject = new UserProject();
            $UserProject->ProjectId = $projectid;
            $UserProject->UserId = $userid;
            if (!$UserProject->Exists()) {
                $UserProject->Role = $role;
                $UserProject->EmailType = 1;
                $UserProject->Save();
            }
        }

        $db = Database::getInstance();

        // Remove the user
        if ($removeuser) {
            DB::delete('DELETE FROM user2project WHERE userid=? AND projectid=?', [$userid, $projectid]);
        }

        // Update the user
        if ($updateuser) {
            $UserProject = new UserProject();
            $UserProject->ProjectId = $projectid;
            $UserProject->UserId = $userid;

            $UserProject->Role = $role;
            $UserProject->EmailType = $emailtype;
            $UserProject->Save();
        }

        // Import the users from CVS
        if ($importUsers) {
            $contents = file_get_contents($_FILES['cvsUserFile']['tmp_name']);
            if (strlen($contents) > 0) {
                $id = 0;
                $pos = 0;
                $pos2 = strpos($contents, "\n");
                while ($pos !== false) {
                    $line = substr($contents, $pos, $pos2 - $pos);

                    $email = '';
                    $svnlogin = '';
                    $firstname = '';
                    $lastname = '';

                    // first is the svnuser
                    $possvn = strpos($line, ':');
                    if ($possvn !== false) {
                        $svnlogin = trim(substr($line, 0, $possvn));

                        $posemail = strpos($line, ':', $possvn + 1);
                        if ($posemail !== false) {
                            $email = trim(substr($line, $possvn + 1, $posemail - $possvn - 1));

                            $name = substr($line, $posemail + 1);
                            $posname = strpos($name, ',');
                            if ($posname !== false) {
                                $name = substr($name, 0, $posname);
                            }

                            $name = trim($name);

                            // Find the firstname
                            $posfirstname = strrpos($name, ' ');
                            if ($posfirstname !== false) {
                                $firstname = trim(substr($name, 0, $posfirstname));
                                $lastname = trim(substr($name, $posfirstname));
                            } else {
                                $firstname = $name;
                            }
                        } else {
                            $email = trim(substr($line, $possvn + 1));
                        }
                    }

                    if (strlen($email) > 0 && $email != 'kitware@kitware.com') {
                        $xml .= '<cvsuser>';
                        $xml .= '<email>' . $email . '</email>';
                        $xml .= '<cvslogin>' . $svnlogin . '</cvslogin>';
                        $xml .= '<firstname>' . $firstname . '</firstname>';
                        $xml .= '<lastname>' . $lastname . '</lastname>';
                        $xml .= '<id>' . $id . '</id>';
                        $xml .= '</cvsuser>';
                        $id++;
                    }

                    $pos = $pos2;
                    $pos2 = strpos($contents, "\n", $pos2 + 1);
                }
            } else {
                echo 'Cannot parse CVS users file';
            }
        }

        $sql = 'SELECT id, name FROM project';
        $params = [];
        if (!$current_user->admin) {
            $sql .= ' WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid=? AND role>0)';
            $params[] = intval(Auth::id());
        }
        $sql .= ' ORDER BY name';
        $projects = $db->executePrepared($sql, $params);
        foreach ($projects as $project_array) {
            $xml .= '<availableproject>';
            $xml .= add_XML_value('id', $project_array['id']);
            $xml .= add_XML_value('name', $project_array['name']);
            if ($project_array['id'] == $projectid) {
                $xml .= add_XML_value('selected', '1');
            }
            $xml .= '</availableproject>';
        }

        // If we have a project id
        if ($projectid > 0) {
            $project = new Project();
            $project->Id = $projectid;
            $projectname = $project->GetName();
            $xml .= '<project>';
            $xml .= add_XML_value('id', $projectid);
            $xml .= add_XML_value('name', $projectname);
            $xml .= add_XML_value('name_encoded', urlencode($projectname));
            $xml .= '</project>';

            // List the users for that project
            $user = $db->executePrepared('
                        SELECT
                            u.id,
                            u.firstname,
                            u.lastname,
                            u.email,
                            up.role,
                            up.emailtype
                        FROM
                            user2project AS up,
                            users AS u
                        WHERE
                            u.id=up.userid
                            AND up.projectid=?
                        ORDER BY u.firstname ASC
                    ', [intval($projectid)]);
            add_last_sql_error('ManageProjectRole');

            $i = 0;
            foreach ($user as $user_array) {
                $userid = intval($user_array['id']);
                $xml .= '<user>';

                if ($i % 2 === 0) {
                    $xml .= add_XML_value('bgcolor', '#CADBD9');
                } else {
                    $xml .= add_XML_value('bgcolor', '#FFFFFF');
                }
                $i++;
                $xml .= add_XML_value('id', $userid);
                $xml .= add_XML_value('firstname', $user_array['firstname']);
                $xml .= add_XML_value('lastname', $user_array['lastname']);
                $xml .= add_XML_value('email', $user_array['email']);

                $xml .= add_XML_value('role', $user_array['role']);
                $xml .= add_XML_value('emailtype', $user_array['emailtype']);

                $xml .= '</user>';
            }
        }

        if ((bool) config('require_full_email_when_adding_user')) {
            $xml .= add_XML_value('fullemail', '1');
        }
        if ((config('auth.project_admin_registration_form_enabled') === true) || $current_user->admin) {
            $xml .= add_XML_value('canRegister', '1');
        }
        $xml .= '</cdash>';

        return $this->view('cdash', 'Project Roles')
            ->with('xsl', true)
            ->with('xsl_content', generate_XSLT($xml, base_path() . '/app/cdash/public/manageProjectRoles', true))
            ->with('project', $project);
    }

    /**
     * Return the list of site maintainers for a given project
     */
    private static function find_site_maintainers(int $projectid): array
    {
        // Get all the users with the 'Site maintainer' role for this project.
        $project = EloquentProject::findOrFail($projectid);
        $userids = $project->siteMaintainers()->pluck('userid')->toArray();

        // Next, get the users maintaining specific sites that have received
        // submissions in the past 48 hours.
        $db = Database::getInstance();
        $submittime_UTCDate = gmdate(FMT_DATETIME, time() - 3600 * 48);
        $site2project = $db->executePrepared('
                            SELECT DISTINCT userid
                            FROM site2user
                            WHERE siteid IN (
                                SELECT siteid
                                FROM build
                                WHERE
                                    projectid=?
                                     AND submittime>?
                            )', [$projectid, $submittime_UTCDate]);
        foreach ($site2project as $site2project_array) {
            $userids[] = intval($site2project_array['userid']);
        }
        return array_unique($userids);
    }

    private function register_user($projectid, $email, $firstName, $lastName)
    {
        if (config('auth.project_admin_registration_form_enabled') === false) {
            return '<error>Users cannot be registered via this form at the current time.</error>';
        }

        $UserProject = new UserProject();
        $UserProject->ProjectId = $projectid;

        $user = User::where('email', $email)->first();
        // Check if the user is already registered
        if ($user) {
            $userid = $user->id;
            // Check if the user has been registered to the project
            $UserProject->UserId = $userid;
            if (!$UserProject->Exists()) {
                // not registered

                // We register the user to the project
                $UserProject->Role = 0;
                $UserProject->EmailType = 1;
                $UserProject->Save();

                if (strlen(pdo_error()) > 0) {
                    throw new RuntimeException(pdo_error());
                }

                return '';
            }
            return '<error>User ' . $email . ' already registered.</error>';
        } // already registered

        // Register the user
        // Create a new password
        $pass = Str::password(10);
        $passwordHash = password_hash($pass, PASSWORD_DEFAULT);

        $user = new User();
        $user->password = $passwordHash;
        $user->email = $email;
        $user->firstname = $firstName;
        $user->lastname = $lastName;
        $user->save();
        $userid = $user->id;

        // Insert the user into the project
        $UserProject->UserId = $userid;
        $UserProject->ProjectId = $projectid;
        $UserProject->Role = 0;
        $UserProject->EmailType = 1;
        $UserProject->Save();

        $prefix = '';
        if (strlen($firstName) > 0) {
            $prefix = ' ';
        }

        $project = new Project();
        $project->Id = $projectid;
        $projectname = $project->GetName();

        // Send the email
        $text = 'Hello' . $prefix . $firstName . ",\n\n";
        $text .= 'You have been registered to CDash because you have access to the source repository for ' . $projectname . "\n";
        $text .= 'To access your CDash account: ' . url('/user') . "\n";
        $text .= 'Your login is: ' . $email . "\n";
        $text .= 'Your password is: ' . $pass . "\n\n";
        $text .= 'Generated by CDash.';

        Mail::raw($text, function ($message) use ($email, $projectname) {
            $message->subject('CDash - ' . $projectname . ' : Subscription')
                ->to($email);
        });
    }

    public function ajaxFindUserProject(): View
    {
        $this->setProjectById(intval($_GET['projectid'] ?? -1));

        $search = $_GET['search'] ?? '';
        $params = [];
        if ((bool) config('require_full_email_when_adding_user')) {
            $sql = 'email=?';
            $params[] = $search;
        } else {
            $sql = '(email LIKE ? OR firstname LIKE ? OR lastname LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $params[] = $this->project->Id;
        $users = DB::select('
                      SELECT id, email, firstname, lastname
                      FROM users
                      WHERE
                          ' . $sql . '
                          AND id NOT IN (
                              SELECT userid as id
                              FROM user2project
                              WHERE projectid=?
                          )
                 ', $params);
        return $this->view('admin.ajax-find-user-project')
            ->with('users', $users);
    }
}
