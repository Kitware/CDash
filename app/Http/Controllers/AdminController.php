<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PageTimer;
use App\Services\TestingDay;
use App\Validators\Password;
use CDash\Config;
use CDash\Database;
use CDash\Model\BuildUpdate;
use CDash\Model\Project;
use CDash\Model\Site;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PDO;

require_once 'include/api_common.php';
require_once 'include/ctestparser.php';
require_once 'include/version.php';

class AdminController extends AbstractController
{
    public function viewUpdate(): View
    {
        return view("build.update");
    }

    public function viewUpdatePageContent(): JsonResponse
    {
        $pageTimer = new PageTimer();
        $build = get_request_build();
        if (is_null($build)) {
            return response()->json([]);
        }

        $db = Database::getInstance();

        $project = new Project();
        $project->Id = $build->ProjectId;
        $project->Fill();

        $date = TestingDay::get($project, $build->StartTime);
        $response = begin_JSON_response();
        get_dashboard_JSON($project->Name, $date, $response);
        $response['title'] = "$project->Name : Update";

        // Menu
        $menu_response = [];
        $menu_response['back'] = "index.php?project=$project->Name&date=$date";

        $previous_buildid = $build->GetPreviousBuildId();
        $current_buildid = $build->GetCurrentBuildId();
        $next_buildid = $build->GetNextBuildId();

        if ($previous_buildid > 0) {
            $menu_response['previous'] = "viewUpdate.php?buildid=$previous_buildid";
        } else {
            $menu_response['previous'] = false;
        }

        $menu_response['current'] = "viewUpdate.php?buildid=$current_buildid";

        if ($next_buildid > 0) {
            $menu_response['next'] = "viewUpdate.php?buildid=$next_buildid";
        } else {
            $menu_response['next'] = false;
        }
        $response['menu'] = $menu_response;

        // Build
        $site = new Site();
        $site->Id = $build->SiteId;
        $site_name = $site->GetName();

        $build_response = [];
        $build_response['site'] = $site_name;
        $build_response['siteid'] = $site->Id;
        $build_response['buildname'] = $build->Name;
        $build_response['buildid'] = $build->Id;
        $build_response['buildtime'] = date('D, d M Y H:i:s T', strtotime($build->StartTime . ' UTC'));
        $response['build'] = $build_response;

        // Update
        $update = new BuildUpdate();
        $update->BuildId = $build->Id;
        $update->FillFromBuildId();

        $update_response = [];
        if (strlen($update->Status) > 0 && $update->Status != '0') {
            $update_response['status'] = $update->Status;
        } else {
            $update_response['status'] = ''; // empty status
        }
        $update_response['revision'] = $update->Revision;
        $update_response['priorrevision'] = $update->PriorRevision;
        $update_response['path'] = $update->Path;
        $update_response['revisionurl'] =
            get_revision_url($project->Id, $update->Revision, $update->PriorRevision);
        $update_response['revisiondiff'] =
            get_revision_url($project->Id, $update->PriorRevision, ''); // no prior prior revision...
        $response['update'] = $update_response;

        $directoryarray = [];
        $updatearray1 = [];
        // Create an array so we can sort it
        foreach ($update->GetFiles() as $update_file) {
            $file = [];
            $file['filename'] = $update_file->Filename;
            $file['author'] = $update_file->Author;
            $file['status'] = $update_file->Status;

            // Only display email if the user is logged in.
            if (Auth::check()) {
                if ($update_file->Email == '') {
                    // Try to find author email from repository credentials.
                    $stmt = $db->prepare("
                SELECT email FROM user WHERE id IN (
                  SELECT up.userid FROM user2project AS up, user2repository AS ur
                   WHERE ur.userid=up.userid
                   AND up.projectid=:projectid
                   AND ur.credential=:author
                   AND (ur.projectid=0 OR ur.projectid=:projectid) )
                   LIMIT 1");
                    $stmt->bindParam(':projectid', $project->Id);
                    $stmt->bindParam(':author', $file['author']);
                    $db->execute($stmt);
                    $file['email'] = $stmt ? $stmt->fetchColumn() : '';
                } else {
                    $file['email'] = $update_file->Email;
                }
            } else {
                $file['email'] = '';
            }

            $file['log'] = $update_file->Log;
            $file['revision'] = $update_file->Revision;
            $updatearray1[] = $file;
            $directoryarray[] = substr($update_file->Filename, 0, strrpos($update_file->Filename, '/'));
        }

        $directoryarray = array_unique($directoryarray);

        usort($directoryarray, function ($a, $b) {
            return $a > $b ? 1 : 0;
        });
        usort($updatearray1, function ($a, $b) {
            // Extract directory
            $filenamea = $a['filename'];
            $filenameb = $b['filename'];
            return $filenamea > $filenameb ? 1 : 0;
        });

        $updatearray = [];

        foreach ($directoryarray as $directory) {
            foreach ($updatearray1 as $update) {
                $filename = $update['filename'];
                if (substr($filename, 0, strrpos($filename, '/')) == $directory) {
                    $updatearray[] = $update;
                }
            }
        }

        // These variables represent a list of directories that contain a list of files.
        $updated_files = [];
        $modified_files = [];
        $conflicting_files = [];

        $num_updated_files = 0;
        $num_modified_files = 0;
        $num_conflicting_files = 0;

        foreach ($updatearray as $file) {
            $filename = $file['filename'];
            $filename = str_replace('\\', '/', $filename);
            $directory = substr($filename, 0, strrpos($filename, '/'));

            $pos = strrpos($filename, '/');
            if ($pos !== false) {
                $filename = substr($filename, $pos + 1);
            }

            $baseurl = $project->BugTrackerFileUrl;
            if (empty($baseurl)) {
                $baseurl = $project->BugTrackerUrl;
            }

            $log = $file['log'];
            $status = $file['status'];
            $revision = $file['revision'];
            $log = str_replace("\r", ' ', $log);
            $log = str_replace("\n", ' ', $log);
            // Do this twice so that <something> ends up as
            // &amp;lt;something&amp;gt; because it gets sent to a
            // javascript function not just displayed as html
            $log = XMLStrFormat($log); // Apparently no need to do this twice anymore
            $log = XMLStrFormat($log);

            $log = trim($log);

            $file['log'] = $log;
            $file['filename'] = $filename;
            $file['bugid'] = '0';
            $file['bugpos'] = '0';
            // This field is redundant because of the way our data is organized.
            unset($file['status']);

            if ($status == 'UPDATED') {
                $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename, $revision);
                $diff_url = XMLStrFormat($diff_url);
                $file['diffurl'] = $diff_url;
                $this->add_file($file, $directory, $updated_files);
                $num_updated_files++;
            } elseif ($status == 'MODIFIED') {
                $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename);
                $diff_url = XMLStrFormat($diff_url);
                $file['diffurl'] = $diff_url;
                $this->add_file($file, $directory, $modified_files);
                $num_modified_files++;
            } else {
                //CONFLICTED
                $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename);
                $diff_url = XMLStrFormat($diff_url);
                $file['diffurl'] = $diff_url;
                $this->add_file($file, $directory, $conflicting_files);
                $num_conflicting_files++;
            }
        }

        $update_groups = [
            [
                'description' => "$project->Name Updated Files ($num_updated_files)",
                'directories' => $updated_files
            ],
            [
                'description' => "Modified Files ($num_modified_files)",
                'directories' => $modified_files
            ],
            [
                'description' => "Conflicting Files ($num_conflicting_files)",
                'directories' => $conflicting_files
            ]
        ];
        $response['updategroups'] = $update_groups;

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    private function add_file($file, string $directory, array &$list_of_files): void
    {
        $idx = array_search($directory, array_column($list_of_files, 'name'));
        if ($idx === false) {
            $d = [];
            $d['name'] = $directory;
            $d['files'] = [$file];
            $list_of_files[] = $d;
        } else {
            $list_of_files[$idx]['files'][] = $file;
        }
    }

    public function import(): View
    {
        @set_time_limit(0);

        checkUserPolicy(0); // only admin

        //get date info here
        @$dayFrom = $_POST['dayFrom'];
        if (!isset($dayFrom)) {
            $dayFrom = date('d', strtotime('yesterday'));
            $monthFrom = date('m', strtotime('yesterday'));
            $yearFrom = date('Y', strtotime('yesterday'));
            $dayTo = date('d');
            $yearTo = date('Y');
            $monthTo = date('m');
        } else {
            $dayFrom = intval($dayFrom);
            $monthFrom = intval($_POST['monthFrom']);
            $yearFrom = intval($_POST['yearFrom']);
            $dayTo = intval($_POST['dayTo']);
            $monthTo = intval($_POST['monthTo']);
            $yearTo = intval($_POST['yearTo']);
        }

        $xml = begin_XML_for_XSLT();
        $xml .= '<backurl>manageBackup.php</backurl>';
        $xml .= '<title>CDash - Import</title>';
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Import Dart1</menusubtitle>';

        $db = Database::getInstance();

        $project = $db->executePrepared('SELECT name, id FROM project ORDER BY id');
        $projName = '';
        foreach ($project as $project_array) {
            $projName = $project_array['name'];
            $xml .= '<project>';
            $xml .= '<name>' . $projName . '</name>';
            $xml .= '<id>' . $project_array['id'] . '</id>';
            $xml .= '</project>';
        }

        $xml .= '<dayFrom>' . $dayFrom . '</dayFrom>';
        $xml .= '<monthFrom>' . $monthFrom . '</monthFrom>';
        $xml .= '<yearFrom>' . $yearFrom . '</yearFrom>';
        $xml .= '<dayTo>' . $dayTo . '</dayTo>';
        $xml .= '<monthTo>' . $monthTo . '</monthTo>';
        $xml .= '<yearTo>' . $yearTo . '</yearTo>';
        $xml .= '</cdash>';

        @$Submit = $_POST['Submit'];
        if ($Submit) {
            $directory = htmlspecialchars($_POST['directory']);
            $projectid = intval($_POST['project']);

            // Checks
            if (!isset($directory) || strlen($directory) < 3) {
                return view('cdash', [
                    'xsl' => true,
                    'xsl_content' => 'Not a valid directory!',
                    'title' => 'Import'
                ]);
            }

            if ($projectid === 0) {
                return view('cdash', [
                    'xsl' => true,
                    'xsl_content' => "Use your browser's Back button, and select a valid project.",
                    'title' => 'Import'
                ]);
            }

            $output = 'Import for Project: ' . get_project_name($projectid) . '<br>';

            $directory = str_replace('\\\\', '/', $directory);
            if (!file_exists($directory) || !str_contains($directory, 'Sites')) {
                return view('cdash', [
                    'xsl' => true,
                    'xsl_content' => "Error: $directory is not a valid path to Dart XML data on the server.<br>\n",
                    'title' => 'Import'
                ]);
            }
            $startDate = mktime(0, 0, 0, $monthFrom, $dayFrom, $yearFrom);
            $endDate = mktime(0, 0, 0, $monthTo, $dayTo, $yearTo);
            $numDays = ($endDate - $startDate) / (24 * 3600) + 1;
            for ($i = 0; $i < $numDays; $i++) {
                $currentDay = date(FMT_DATE, mktime(0, 0, 0, $monthFrom, $dayFrom + $i, $yearFrom));
                $output .= "Gathering XML files for $currentDay...  $directory/*/*/$currentDay-*/XML/*.xml <br>\n";
                $files = glob($directory . "/*/*/$currentDay-*/XML/*.xml");
                $numFiles = count($files);
                $output .= "$numFiles found<br>\n";

                foreach ($files as $file) {
                    if (strlen($file) == 0) {
                        continue;
                    }
                    $handle = fopen($file, 'r');
                    ctest_parse($handle, $projectid);
                    fclose($handle);
                    unset($handle);
                }
                $output .= '<br>Done for the day ' . $currentDay . "<br>\n";
            }
            $output .= '<a href=index.php?project=' . urlencode($projName) . ">Back to $projName dashboard</a>\n";
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => $output,
                'title' => 'Import'
            ]);
        }

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/import', true),
            'title' => 'Import'
        ]);
    }

    public function importBackup(): View|RedirectResponse
    {
        $policy = checkUserPolicy(0); // only admin
        if ($policy !== true) {
            return $policy;
        }

        @set_time_limit(0);

        $xml = begin_XML_for_XSLT();
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Backups</menusubtitle>';
        $xml .= '<backurl>manageBackup.php</backurl>';

        @$Submit = $_POST['Submit'];

        @$filemask = $_POST['filemask'];
        if ($filemask == '') {
            $filemask = '*.xml';
        }

        if ($Submit && $filemask) {
            $filelist = glob(Storage::path('inbox') . "/$filemask");
            $i = 0;
            $n = count($filelist);

            add_log('before loop n=' . $n, 'importBackup.php');

            foreach ($filelist as $filename) {
                ++$i;
                $projectid = -1;

                add_log('looping i=' . $i . ' filename=' . $filename, 'importBackup.php');

                # split on path separator
                $pathParts = explode(PATH_SEPARATOR, $filename);

                # split on cdash separator "_"
                if (count($pathParts) >= 1) {
                    $cdashParts = preg_split('#_#', $pathParts[count($pathParts) - 1]);
                    $projectid = get_project_id($cdashParts[0]);
                }

                if ($projectid != -1) {
                    $handle = fopen($filename, 'r');
                    if ($handle) {
                        ctest_parse($handle, $projectid);
                        fclose($handle);
                        unset($handle);
                    } else {
                        add_log('could not open file filename=' . $filename, 'importBackup.php', LOG_ERR);
                    }
                } else {
                    add_log('could not determine projectid from filename=' . $filename, 'importBackup.php', LOG_ERR);
                }
            }

            add_log('after loop n=' . $n, 'importBackup.php');

            $alert = 'Import backup complete. ' . $i . ' files processed.';
            $xml .= add_XML_value('alert', $alert);
        }
        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/importBackup', true),
            'title' => 'Import Backups'
        ]);
    }

    public function manageBackup(): View|RedirectResponse
    {
        $policy = checkUserPolicy(0); // only admin
        if ($policy !== true) {
            return $policy;
        }

        @set_time_limit(0);

        $xml = begin_XML_for_XSLT();
        $xml .= '<title>CDash - Backup</title>';
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Backup</menusubtitle>';
        $xml .= '<backurl>user.php</backurl>';
        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/manageBackup', true),
            'title' => 'Manage Backup'
        ]);
    }

    public function removeBuilds(): View|RedirectResponse
    {
        $config = Config::getInstance();

        @set_time_limit(0);

        $policy = checkUserPolicy(0); // only admin
        if ($policy !== true) {
            return $policy;
        }

        @$projectid = $_GET['projectid'];
        if ($projectid != null) {
            $projectid = intval($projectid);
        }

        $db = Database::getInstance();

        //get date info here
        @$dayTo = intval($_POST['dayFrom']);
        if (empty($dayTo)) {
            $time = strtotime('2000-01-01 00:00:00');

            if (isset($projectid)) {
                // find the first and last builds
                $startttime = $db->executePreparedSingleRow('
                                  SELECT starttime
                                  FROM build
                                  WHERE projectid=?
                                  ORDER BY starttime ASC
                                  LIMIT 1
                              ', [intval($projectid)]);
                if (!empty($startttime)) {
                    $time = strtotime($startttime['starttime']);
                }
            }
            $dayFrom = date('d', $time);
            $monthFrom = date('m', $time);
            $yearFrom = date('Y', $time);
            $dayTo = date('d');
            $yearTo = date('Y');
            $monthTo = date('m');
        } else {
            $dayFrom = intval($_POST['dayFrom']);
            $monthFrom = intval($_POST['monthFrom']);
            $yearFrom = intval($_POST['yearFrom']);
            $dayTo = intval($_POST['dayTo']);
            $monthTo = intval($_POST['monthTo']);
            $yearTo = intval($_POST['yearTo']);
        }

        $xml = '<cdash>';
        $xml .= '<cssfile>' . $config->get('CDASH_CSS_FILE') . '</cssfile>';
        $xml .= '<version>' . $config->get('CDASH_VERSION') . '</version>';
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Remove Builds</menusubtitle>';
        $xml .= '<backurl>manageBackup.php</backurl>';

        // List the available projects
        $projects = $db->executePrepared('SELECT id, name FROM project');
        foreach ($projects as $projects_array) {
            $xml .= '<availableproject>';
            $xml .= add_XML_value('id', $projects_array['id']);
            $xml .= add_XML_value('name', $projects_array['name']);
            if ($projects_array['id'] == $projectid) {
                $xml .= add_XML_value('selected', '1');
            }
            $xml .= '</availableproject>';
        }

        $xml .= '<dayFrom>' . $dayFrom . '</dayFrom>';
        $xml .= '<monthFrom>' . $monthFrom . '</monthFrom>';
        $xml .= '<yearFrom>' . $yearFrom . '</yearFrom>';
        $xml .= '<dayTo>' . $dayTo . '</dayTo>';
        $xml .= '<monthTo>' . $monthTo . '</monthTo>';
        $xml .= '<yearTo>' . $yearTo . '</yearTo>';

        @$submit = $_POST['Submit'];

        // Delete the builds
        if (isset($submit)) {
            $build = $db->executePrepared("
                         SELECT id
                         FROM build
                         WHERE
                             projectid=?
                             AND parentid IN (0, -1)
                             AND starttime<=?||'-'||?||'-'||?||' 00:00:00'
                             AND starttime>=?||'-'||?||'-'||?||' 00:00:00'
                         ORDER BY starttime ASC
                     ", [
                        intval($projectid),
                        $yearTo,
                        $monthTo,
                        $dayTo,
                        $yearFrom,
                        $monthFrom,
                        $dayFrom
                    ]);

            $builds = array();
            foreach ($build as $build_array) {
                $builds[] = intval($build_array['id']);
            }

            remove_build_chunked($builds);
            $xml .= add_XML_value('alert', 'Removed ' . count($builds) . ' builds.');
        }
        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/removeBuilds', true),
            'title' => 'Remove Builds'
        ]);
    }

    public function upgrade()
    {
        $config = Config::getInstance();

        @set_time_limit(0);

        $policy = checkUserPolicy(0); // only admin
        if ($policy !== true) {
            return $policy;
        }

        $xml = begin_XML_for_XSLT();
        $xml .= '<backurl>user.php</backurl>';
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Maintenance</menusubtitle>';

        // Should be the database version not the current on
        $version = pdo_query('SELECT major,minor FROM version');
        $version_array = pdo_fetch_array($version);
        $xml .= '<minversion>' . $version_array['major'] . '.' . $version_array['minor'] . '</minversion>';

        @$CreateDefaultGroups = $_POST['CreateDefaultGroups'];
        @$AssignBuildToDefaultGroups = $_POST['AssignBuildToDefaultGroups'];
        @$FixBuildBasedOnRule = $_POST['FixBuildBasedOnRule'];
        @$DeleteBuildsWrongDate = $_POST['DeleteBuildsWrongDate'];
        @$CheckBuildsWrongDate = $_POST['CheckBuildsWrongDate'];
        @$ComputeTestTiming = $_POST['ComputeTestTiming'];
        @$ComputeUpdateStatistics = $_POST['ComputeUpdateStatistics'];

        @$Upgrade = $_POST['Upgrade'];
        @$Cleanup = $_POST['Cleanup'];
        @$Dependencies = $_POST['Dependencies'];
        @$Audit = $_POST['Audit'];
        @$ClearAudit = $_POST['Clear'];

        $configFile = $config->get('CDASH_ROOT_DIR') . "/AuditReport.log";

        if (!config('database.default')) {
            $db_type = 'mysql';
        } else {
            $db_type = config('database.default');
        }

        if (isset($_GET['upgrade-tables'])) {
            // Apply all the patches
            foreach (glob($config->get('CDASH_ROOT_DIR') . "/sql/$db_type/cdash-upgrade-*.sql") as $filename) {
                $file_content = file($filename);
                $query = '';
                foreach ($file_content as $sql_line) {
                    $tsl = trim($sql_line);

                    if (($sql_line != '') && (substr($tsl, 0, 2) != '--') && (substr($tsl, 0, 1) != '#')) {
                        $query .= $sql_line;
                        if (preg_match("/;\s*$/", $sql_line)) {
                            $query = str_replace(';', '', "$query");
                            $result = pdo_query($query);
                            if (!$result) {
                                if ($db_type != 'pgsql') {
                                    // postgresql < 9.1 doesn't know CREATE TABLE IF NOT EXISTS so we don't die

                                    die(pdo_error());
                                }
                            }
                            $query = '';
                        }
                    }
                }
            }
            return;
        }

        // 2.2 Upgrade
        if (isset($_GET['upgrade-2-2'])) {
            AddTableIndex('updatefile', 'author');

            // We need to move the buildupdate build ids to the build2update table
            $query = pdo_query('SELECT buildid FROM buildupdate');
            while ($query_array = pdo_fetch_array($query)) {
                pdo_query("INSERT INTO build2update (buildid,updateid) VALUES ('" . $query_array['buildid'] . "','" . $query_array['buildid'] . "')");
            }
            RemoveTableIndex('buildupdate', 'buildid');
            RenameTableField('buildupdate', 'buildid', 'id', 'int(11)', 'bigint', '0');
            AddTablePrimaryKey('buildupdate', 'id');
            ModifyTableField('buildupdate', 'id', 'int(11)', 'bigint', '', true, true);
            RenameTableField('updatefile', 'buildid', 'updateid', 'int(11)', 'bigint', '0');

            AddTableField('site', 'outoforder', 'tinyint(1)', 'smallint', '0');

            // Set the database version
            setVersion();

            // Put that the upgrade is done in the log
            add_log('Upgrade done.', 'upgrade-2-2');
            return;
        }

        // 2.4 Upgrade
        if (isset($_GET['upgrade-2-4'])) {
            // Support for subproject groups
            AddTableField('subproject', 'groupid', 'int(11)', 'bigint', '0');
            AddTableIndex('subproject', 'groupid');
            AddTableField('subprojectgroup', 'position', 'int(11)', 'bigint', '0');
            AddTableIndex('subprojectgroup', 'position');
            RemoveTableField('subproject', 'core');
            RemoveTableField('project', 'coveragethreshold2');

            // Support for larger types
            ModifyTableField('buildfailure', 'workingdirectory', 'VARCHAR( 512)', 'VARCHAR( 512 )', '', true, false);
            ModifyTableField('buildfailure', 'outputfile', 'VARCHAR( 512)', 'VARCHAR( 512 )', '', true, false);

            // Support for parent builds
            AddTableField('build', 'parentid', 'int(11)', 'int', '0');
            AddTableIndex('build', 'parentid');

            // Cache configure results similar to build & test
            AddTableField('build', 'configureerrors', 'smallint(6)', 'smallint', '-1');
            AddTableField('build', 'configurewarnings', 'smallint(6)', 'smallint', '-1');

            // Add new multi-column index to build table.
            // This improves the rendering speed of overview.php.
            $multi_index = array('projectid', 'parentid', 'starttime');
            AddTableIndex('build', $multi_index);

            // Support for dynamic BuildGroups.
            AddTableField('buildgroup', 'type', 'varchar(20)', 'character varying(20)', 'Daily');
            AddTableField('build2grouprule', 'parentgroupid', 'int(11)', 'bigint', '0');

            // Support for pull request notifications.
            AddTableField('build', 'notified', 'tinyint(1)', 'smallint', '0');

            // Better caching of buildfailures.
            UpgradeBuildFailureTable('buildfailure', 'buildfailuredetails');
            AddTableIndex('buildfailure', 'detailsid');

            // Add key to label2test.
            // This speeds up viewTest API for builds with lots of tests & labels.
            AddTableIndex('label2test', 'testid');

            // Better caching of build & test time, particularly for parent builds.
            $query = 'SELECT configureduration FROM build LIMIT 1';
            $dbTest = pdo_query($query);
            if ($dbTest === false) {
                AddTableField('build', 'configureduration', 'float(7,2)',
                    'numeric(7,2)', '0.00');
                UpgradeConfigureDuration();
                UpgradeTestDuration();
            }
            // Distinguish build step duration from (end time - start time).
            $query = 'SELECT buildduration FROM build LIMIT 1';
            $dbTest = pdo_query($query);
            if ($dbTest === false) {
                AddTableField('build', 'buildduration', 'int(11)', 'integer', '0');
                UpgradeBuildDuration();
            }

            // Support for marking a build as "done".
            AddTableField('build', 'done', 'tinyint(1)', 'smallint', '0');

            // Add a unique uuid field to the build table.
            $uuid_check = pdo_query('SELECT uuid FROM build LIMIT 1');
            if ($uuid_check === false) {
                AddTableField('build', 'uuid', 'varchar(36)', 'character varying(36)', false);
                if ($db_type === 'pgsql') {
                    pdo_query('ALTER TABLE build ADD UNIQUE (uuid)');
                } else {
                    pdo_query('ALTER TABLE build ADD UNIQUE KEY (uuid)');
                }

                // Also add a new unique constraint to the subproject table.
                if ($db_type === 'pgsql') {
                    pdo_query('ALTER TABLE subproject ADD UNIQUE (name, projectid, endtime)');
                    pdo_query('CREATE INDEX "subproject_unique2" ON "subproject" ("name", "projectid", "endtime")');
                } else {
                    pdo_query('ALTER TABLE subproject ADD UNIQUE KEY (name, projectid, endtime)');
                }
            }

            // Support for subproject path.
            AddTableField('subproject', 'path', 'varchar(512)', 'character varying(512)', '');

            // Remove the errorlog from the DB (we're all log files now).
            pdo_query('DROP TABLE IF EXISTS errorlog');

            // Option to pass label filters from index.php to test pages.
            AddTableField('project', 'sharelabelfilters', 'tinyint(1)', 'smallint', '0');

            // Summarize the number of dynamic analysis defects each build found.
            PopulateDynamicAnalysisSummaryTable();

            // Add index to buildupdate::revision in support of this filter.
            AddTableIndex('buildupdate', 'revision');

            // Store CTEST_CHANGE_ID in the build table.
            AddTableField('build', 'changeid', 'varchar(40)', 'character varying(40)', '');

            // Add unique constraints to the *diff tables.
            AddUniqueConstraintToDiffTables();

            // Set the database version
            setVersion();

            // Put that the upgrade is done in the log
            add_log('Upgrade done.', 'upgrade-2-4');
            return;
        }

        // 2.6 Upgrade
        if (isset($_GET['upgrade-2-6'])) {
            // Add index to label2test::buildid to improve performance of remove_build()
            AddTableIndex('label2test', 'buildid');

            // Expand size of password field to 255 characters.
            if (config('database.default') != 'pgsql') {
                ModifyTableField('password', 'password', 'VARCHAR( 255 )', 'VARCHAR( 255 )', '', true, false);
                ModifyTableField('user', 'password', 'VARCHAR( 255 )', 'VARCHAR( 255 )', '', true, false);
                ModifyTableField('usertemp', 'password', 'VARCHAR( 255 )', 'VARCHAR( 255 )', '', true, false);
            }

            // Restructure configure table.
            // This reduces the footprint of this table and allows multiple builds
            // to share a configure.
            if (!pdo_query('SELECT id FROM configure LIMIT 1')) {
                // Add id and crc32 columns to configure table.
                if (config('database.default') != 'pgsql') {
                    pdo_query(
                        'ALTER TABLE configure
                ADD id int(11) NOT NULL AUTO_INCREMENT,
                ADD crc32 bigint(20) NOT NULL DEFAULT \'0\',
                ADD PRIMARY KEY(id)');
                } else {
                    pdo_query(
                        'ALTER TABLE configure
                ADD id SERIAL NOT NULL,
                ADD crc32 BIGINT DEFAULT \'0\' NOT NULL,
                ADD PRIMARY KEY (id)');
                }

                // Populate build2configure table.
                PopulateBuild2Configure('configure', 'build2configure');

                // Add unique constraint to crc32 column.
                if ($db_type === 'pgsql') {
                    pdo_query('ALTER TABLE configure ADD UNIQUE (crc32)');
                } else {
                    pdo_query('ALTER TABLE configure ADD UNIQUE KEY (crc32)');
                }

                // Remove columns from configure that have been moved to build2configure.
                if (config('database.default') == 'pgsql') {
                    pdo_query('ALTER TABLE "configure"
                        DROP COLUMN "buildid",
                        DROP COLUMN "starttime",
                        DROP COLUMN "endtime"');
                } else {
                    pdo_query('ALTER TABLE configure
                        DROP buildid,
                        DROP starttime,
                        DROP endtime');
                }

                // Change configureerror to use configureid instead of buildid.
                UpgradeConfigureErrorTable('configureerror', 'build2configure');
            }

            // Support for authenticated submissions.
            AddTableField('project', 'authenticatesubmissions', 'tinyint(1)', 'smallint', '0');

            // Add position field to subproject table.
            AddTableField('subproject', 'position', 'smallint(6) unsigned', 'smallint', '0');

            // Support for bugtracker issue creation.
            AddTableField('project', 'bugtrackernewissueurl', 'varchar(255)', 'character varying(255)', '');
            AddTableField('project', 'bugtrackertype', 'varchar(16)', 'character varying(16)', '');

            // Add new unique constraint to the site table.
            AddUniqueConstraintToSiteTable('site');

            // Set the database version
            setVersion();

            // Put that the upgrade is done in the log
            add_log('Upgrade done.', 'upgrade-2-6');
            return;
        }

        // 2.8 Upgrade
        if (isset($_GET['upgrade-2-8'])) {
            // Add a 'recheck' field to the pendingsubmission table.
            AddTableField('pending_submissions', 'recheck', 'tinyint(1)', 'smallint', '0');

            // Migrate from buildtesttime.time to build.testduration
            if (!pdo_query('SELECT testduration FROM build LIMIT 1')) {
                // Add testduration column to build table.
                AddTableField('build', 'testduration', 'int(11)', 'integer', '0');

                // Migrate values from buildtesttime.time to build.testduration.
                PopulateTestDuration();

                // Change build.configureduration from float to int
                ModifyTableField('build', 'configureduration', 'int(11)', 'integer', '0', true, false);
            }

            // Set the database version
            setVersion();

            // Put that the upgrade is done in the log
            add_log('Upgrade done.', 'upgrade-2-8');
            $_GET['upgrade-3-0'] = 1;
        }

        // 3.0 Upgrade
        if (isset($_GET['upgrade-3-0'])) {
            // Add Laravel required columns to user and password tables.
            AddTableField('user', 'updated_at', 'TIMESTAMP', 'TIMESTAMP', '1980-01-01 00:00:00');
            AddTableField('user', 'created_at', 'TIMESTAMP', 'TIMESTAMP', '1980-01-01 00:00:00');
            AddTableField('user', 'remember_token', 'varchar(100)', 'character varying(16)', 'NULL');
            AddTableField('password', 'updated_at', 'TIMESTAMP', 'TIMESTAMP', '1980-01-01 00:00:00');
            AddTableField('password', 'created_at', 'TIMESTAMP', 'TIMESTAMP', '1980-01-01 00:00:00');

            // Call artisan to run Laravel database migrations.
            Artisan::call('migrate --force');

            // Set the database version
            setVersion();

            // Put that the upgrade is done in the log
            add_log('Upgrade done.', 'upgrade-3-0');
            return;
        }

        // When adding new tables they should be added to the SQL installation file
        // and here as well
        if ($Upgrade) {
            // check if the backup directory is writable
            if (!is_writable(Storage::path('inbox'))) {
                $xml .= '<backupwritable>0</backupwritable>';
            } else {
                $xml .= '<backupwritable>1</backupwritable>';
            }

            // check if the log directory is writable
            if ($config->get('CDASH_LOG_FILE') !== false && !is_writable($config->get('CDASH_LOG_DIRECTORY'))) {
                $xml .= '<logwritable>0</logwritable>';
            } else {
                $xml .= '<logwritable>1</logwritable>';
            }

            // check if the upload directory is writable
            if (!is_writable($config->get('CDASH_UPLOAD_DIRECTORY'))) {
                $xml .= '<uploadwritable>0</uploadwritable>';
            } else {
                $xml .= '<uploadwritable>1</uploadwritable>';
            }

            $xml .= '<upgrade>1</upgrade>';
        }

        // Compress the test output
        if (isset($_POST['CompressTestOutput'])) {
            // Do it slowly so we don't take all the memory
            $query = pdo_query('SELECT count(*) FROM testoutput');
            $query_array = pdo_fetch_array($query);
            $ntests = $query_array[0];
            $ngroup = 1024;
            for ($i = 0; $i < $ntests; $i += $ngroup) {
                $query = pdo_query('SELECT id,output FROM testoutput ORDER BY id ASC LIMIT ' . $ngroup . ' OFFSET ' . $i);
                while ($query_array = pdo_fetch_array($query)) {
                    // Try uncompressing to see if it's already compressed
                    if (@gzuncompress($query_array['output']) === false) {
                        $compressed = pdo_real_escape_string(gzcompress($query_array['output']));
                        pdo_query("UPDATE testoutput SET output='" . $compressed . "' WHERE id=" . $query_array['id']);
                        echo pdo_error();
                    }
                }
            }
        }

        // Compute the testtime
        if ($ComputeTestTiming) {
            @$TestTimingDays = $_POST['TestTimingDays'];
            if ($TestTimingDays != null) {
                $TestTimingDays = pdo_real_escape_numeric($TestTimingDays);
            }
            if (is_numeric($TestTimingDays) && $TestTimingDays > 0) {
                ComputeTestTiming($TestTimingDays);
                $xml .= add_XML_value('alert', 'Timing for tests has been computed successfully.');
            } else {
                $xml .= add_XML_value('alert', 'Wrong number of days.');
            }
        }

        // Compute the user statistics
        if ($ComputeUpdateStatistics) {
            @$UpdateStatisticsDays = $_POST['UpdateStatisticsDays'];
            if ($UpdateStatisticsDays != null) {
                $UpdateStatisticsDays = pdo_real_escape_numeric($UpdateStatisticsDays);
            }
            if (is_numeric($UpdateStatisticsDays) && $UpdateStatisticsDays > 0) {
                ComputeUpdateStatistics($UpdateStatisticsDays);
                $xml .= add_XML_value('alert', 'User statistics has been computed successfully.');
            } else {
                $xml .= add_XML_value('alert', 'Wrong number of days.');
            }
        }

        if ($Dependencies) {
            $returnVal = Artisan::call("dependencies:update");
            $xml .= add_XML_value('alert', "The call to update CDash's dependencies was run. The call exited with value: $returnVal");
        }

        if ($Audit) {
            if (!file_exists($configFile)) {
                Artisan::call("schedule:test --name='dependencies:audit'");
            }
            $fileContents = file_get_contents($configFile);
            $xml .= add_XML_value('audit', $fileContents);
        }

        if ($ClearAudit && file_exists($configFile)) {
            unlink($configFile);
        }



        /* Cleanup the database */
        if ($Cleanup) {
            delete_unused_rows('banner', 'projectid', 'project');
            delete_unused_rows('blockbuild', 'projectid', 'project');
            delete_unused_rows('build', 'projectid', 'project');
            delete_unused_rows('buildgroup', 'projectid', 'project');
            delete_unused_rows('labelemail', 'projectid', 'project');
            delete_unused_rows('project2repositories', 'projectid', 'project');
            delete_unused_rows('dailyupdate', 'projectid', 'project');
            delete_unused_rows('projectrobot', 'projectid', 'project');
            delete_unused_rows('submission', 'projectid', 'project');
            delete_unused_rows('subproject', 'projectid', 'project');
            delete_unused_rows('coveragefilepriority', 'projectid', 'project');
            delete_unused_rows('test', 'projectid', 'project');
            delete_unused_rows('user2project', 'projectid', 'project');
            delete_unused_rows('userstatistics', 'projectid', 'project');

            delete_unused_rows('build2configure', 'buildid', 'build');
            delete_unused_rows('build2note', 'buildid', 'build');
            delete_unused_rows('build2test', 'buildid', 'build');
            delete_unused_rows('buildemail', 'buildid', 'build');
            delete_unused_rows('builderror', 'buildid', 'build');
            delete_unused_rows('builderrordiff', 'buildid', 'build');
            delete_unused_rows('buildfailure', 'buildid', 'build');
            delete_unused_rows('buildinformation', 'buildid', 'build');
            delete_unused_rows('buildnote', 'buildid', 'build');
            delete_unused_rows('buildtesttime', 'buildid', 'build');
            delete_unused_rows('configure', 'id', 'build2configure', 'configureid');
            delete_unused_rows('configureerror', 'configureid', 'configure');
            delete_unused_rows('configureerrordiff', 'buildid', 'build');
            delete_unused_rows('coverage', 'buildid', 'build');
            delete_unused_rows('coveragefilelog', 'buildid', 'build');
            delete_unused_rows('coveragesummary', 'buildid', 'build');
            delete_unused_rows('coveragesummarydiff', 'buildid', 'build');
            delete_unused_rows('dynamicanalysis', 'buildid', 'build');
            delete_unused_rows('label2build', 'buildid', 'build');
            delete_unused_rows('subproject2build', 'buildid', 'build');
            delete_unused_rows('summaryemail', 'buildid', 'build');
            delete_unused_rows('testdiff', 'buildid', 'build');

            delete_unused_rows('dynamicanalysisdefect', 'dynamicanalysisid', 'dynamicanalysis');
            delete_unused_rows('subproject2subproject', 'subprojectid', 'subproject');

            delete_unused_rows('dailyupdatefile', 'dailyupdateid', 'dailyupdate');
            delete_unused_rows('coveragefile', 'id', 'coverage', 'fileid');
            delete_unused_rows('coveragefile2user', 'fileid', 'coveragefile');

            delete_unused_rows('dailyupdatefile', 'dailyupdateid', 'dailyupdate');
            delete_unused_rows('test2image', 'outputid', 'testoutput');
            delete_unused_rows('testmeasurement', 'outputid', 'testoutput');
            delete_unused_rows('label2test', 'outputid', 'testoutput');

            $xml .= add_XML_value('alert', 'Database cleanup complete.');
        }

        /* Check the builds with wrong date */
        if ($CheckBuildsWrongDate) {
            $currentdate = time() + 3600 * 24 * 3; // or 3 days away from now
            $forwarddate = date(FMT_DATETIME, $currentdate);

            $builds = pdo_query("SELECT id,name,starttime FROM build WHERE starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
            while ($builds_array = pdo_fetch_array($builds)) {
                echo $builds_array['name'] . '-' . $builds_array['starttime'] . '<br>';
            }
        }

        /* Delete the builds with wrong date */
        if ($DeleteBuildsWrongDate) {
            $currentdate = time() + 3600 * 24 * 3; // or 3 days away from now
            $forwarddate = date(FMT_DATETIME, $currentdate);

            $builds = pdo_query(
                "SELECT id FROM build WHERE parentid IN (0, -1) AND
          starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
            while ($builds_array = pdo_fetch_array($builds)) {
                $buildid = $builds_array['id'];
                remove_build($buildid);
            }
        }

        if ($FixBuildBasedOnRule) {
            // loop through the list of build2group
            $buildgroups = pdo_query('SELECT * from build2group');
            while ($buildgroup_array = pdo_fetch_array($buildgroups)) {
                $buildid = $buildgroup_array['buildid'];

                $build = pdo_query("SELECT * from build WHERE id='$buildid'");
                $build_array = pdo_fetch_array($build);
                $type = $build_array['type'];
                $name = $build_array['name'];
                $siteid = $build_array['siteid'];
                $projectid = $build_array['projectid'];
                $submittime = $build_array['submittime'];

                $build2grouprule = pdo_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                    WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                    AND (b2g.groupid=bg.id AND bg.projectid='$projectid')
                                    AND '$submittime'>b2g.starttime AND ('$submittime'<b2g.endtime OR b2g.endtime='1980-01-01 00:00:00')");
                echo pdo_error();
                if (pdo_num_rows($build2grouprule) > 0) {
                    $build2grouprule_array = pdo_fetch_array($build2grouprule);
                    $groupid = $build2grouprule_array['groupid'];
                    pdo_query("UPDATE build2group SET groupid='$groupid' WHERE buildid='$buildid'");
                }
            }
        }

        if ($CreateDefaultGroups) {
            // Loop throught the projects
            $n = 0;
            $projects = pdo_query('SELECT id FROM project');
            while ($project_array = pdo_fetch_array($projects)) {
                $projectid = $project_array['id'];

                if (pdo_num_rows(pdo_query("SELECT projectid FROM buildgroup WHERE projectid='$projectid'")) == 0) {
                    // Add the default groups
                    pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Nightly','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Nightly Builds')");
                    $id = pdo_insert_id('buildgroup');
                    pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','1','1980-01-01 00:00:00','1980-01-01 00:00:00')");
                    echo pdo_error();
                    pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Continuous','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Continuous Builds')");
                    $id = pdo_insert_id('buildgroup');
                    pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','2','1980-01-01 00:00:00','1980-01-01 00:00:00')");
                    pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Experimental','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Experimental Builds')");
                    $id = pdo_insert_id('buildgroup');
                    pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','3','1980-01-01 00:00:00','1980-01-01 00:00:00')");
                    $n++;
                }
            }

            $xml .= add_XML_value('alert', $n . ' projects have now default groups.');
        } elseif ($AssignBuildToDefaultGroups) {
            // Loop throught the builds
            $builds = pdo_query('SELECT id,type,projectid FROM build WHERE id NOT IN (SELECT buildid as id FROM build2group)');

            while ($build_array = pdo_fetch_array($builds)) {
                $buildid = $build_array['id'];
                $buildtype = $build_array['type'];
                $projectid = $build_array['projectid'];

                $buildgroup_array = pdo_fetch_array(pdo_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'"));

                $groupid = $buildgroup_array['id'];
                pdo_query("INSERT INTO build2group(buildid,groupid) VALUES ('$buildid','$groupid')");
            }

            $xml .= add_XML_value('alert', 'Builds have been added to default groups successfully.');
        }

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/upgrade', true),
            'title' => 'Maintenance'
        ]);
    }

    public function install(): View
    {
        @set_time_limit(0);

        // This is the installation script for CDash
        if (class_exists('XsltProcessor') === false) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => '<font color="#FF0000">Your PHP installation does not support XSL. Please install the XSL extension.</font>',
                'title' => 'Installation'
            ]);
        }

        $config = Config::getInstance();

        if (config('app.env') === 'production') {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'CDash is in production mode. Install cannot be accessed. Set APP_ENV=development in your .env file if you want to access the installation.',
                'title' => 'Installation'
            ]);
        }

        $xml = begin_XML_for_XSLT();

        if (function_exists('curl_init') === false) {
            $xml .= '<extcurl>0</extcurl>';
        } else {
            $xml .= '<extcurl>1</extcurl>';
        }

        if (function_exists('json_encode') === false) {
            $xml .= '<extjson>0</extjson>';
        } else {
            $xml .= '<extjson>1</extjson>';
        }

        if (function_exists('mb_detect_encoding') === false) {
            $xml .= '<extmbstring>0</extmbstring>';
        } else {
            $xml .= '<extmbstring>1</extmbstring>';
        }

        if (class_exists('PDO') === false) {
            $xml .= '<extpdo>0</extpdo>';
        } else {
            $xml .= '<extpdo>1</extpdo>';
        }

        $db_type = config('database.default');
        $database_config = config("database.connections.{$db_type}");
        $db_host = $database_config['host'];
        $db_port = $database_config['port'];
        $db_user = $database_config['username'];
        $db_pass = $database_config['password'];
        $db_name = $database_config['database'];

        if (array_key_exists('unix_socket', $database_config) && $database_config['unix_socket']) {
            $db_connection = 'unix_socket';
        } else {
            $db_connection = 'host';
            if ($db_port != '') {
                $db_host = $db_host . ';port=' . $db_port;
            }
        }

        $xml .= '<connectiondb_type>' . $db_type . '</connectiondb_type>';
        $xml .= '<connectiondb_host>' . $db_host . '</connectiondb_host>';
        $xml .= '<connectiondb_login>' . $db_user . '</connectiondb_login>';
        $xml .= '<connectiondb_name>' . $db_name  . '</connectiondb_name>';

        // Step 1: Check if we can connect to the database
        try {
            $pdo = new PDO("{$db_type}:{$db_connection}={$db_host}", $db_user, $db_pass);
            $xml .= '<connectiondb>1</connectiondb>';
        } catch (Exception) {
            $xml .= '<connectiondb>0</connectiondb>';
        }

        // check if the backup directory is writable
        if (!is_writable(Storage::path('inbox'))) {
            $xml .= '<backupwritable>0</backupwritable>';
        } else {
            $xml .= '<backupwritable>1</backupwritable>';
        }

        // check if the log directory is writable
        if ($config->get('CDASH_LOG_FILE') !== false && !is_writable($config->get('CDASH_LOG_DIRECTORY'))) {
            $xml .= '<logwritable>0</logwritable>';
        } else {
            $xml .= '<logwritable>1</logwritable>';
        }

        // check if the upload directory is writable
        if (!is_writable($config->get('CDASH_UPLOAD_DIRECTORY'))) {
            $xml .= '<uploadwritable>0</uploadwritable>';
        } else {
            $xml .= '<uploadwritable>1</uploadwritable>';
        }

        $installed = false;
        try {
            if (Schema::hasTable(qid('user'))) {
                $xml .= '<database>1</database>';
                $installed = true;
            } else {
                $xml .= '<database>0</database>';
            }
        } catch (Exception) {
            $xml .= '<database>0</database>';
        }

        // If the database already exists and we have all the tables
        if (!$installed) {
            $xml .= '<dashboard_timeframe>24</dashboard_timeframe>';

            // If we should create the tables
            @$Submit = $_POST['Submit'];
            if ($Submit) {
                $admin_email = $_POST['admin_email'];
                $admin_password = $_POST['admin_password'];

                $valid_email = true;

                if (strlen($admin_email) < 6 || !str_contains($admin_email, '@')) {
                    $xml .= '<db_created>0</db_created>';
                    $xml .= "<alert>* Administrator's email should be a valid email address</alert>";
                    $valid_email = false;
                }
                $minimum_password_length = config('cdash.password.min');
                if ($valid_email && strlen($admin_password) < $minimum_password_length) {
                    $xml .= '<db_created>0</db_created>';
                    $xml .= "<alert>* Administrator's password must be at least $minimum_password_length characters</alert>";
                    $valid_email = false;
                }
                if ($valid_email) {
                    $password_validator = new Password;
                    $complexity_count = config('cdash.password.count');
                    $complexity = $password_validator->computeComplexity($admin_password, $complexity_count);
                    $minimum_complexity = config('cdash.password.complexity');
                    if ($complexity < $minimum_complexity) {
                        $xml .= "<alert>* Administrator's password is not complex enough. ";
                        if ($complexity_count > 1) {
                            $xml .= "It must contain at least $complexity_count characters from $minimum_complexity of the following types: uppercase, lowercase, numbers, and symbols.";
                        } else {
                            $xml .= "It must contain at least $minimum_complexity of the following: uppercase, lowercase, numbers, and symbols.";
                        }
                        $xml .= '</alert>';
                        $valid_email = false;
                    }
                }

                if ($valid_email) {
                    $db_created = true;
                    $sql = $db_type === 'mysql' ? "CREATE DATABASE IF NOT EXISTS `{$db_name}`" : "CREATE DATABASE {$db_name}";

                    try {
                        $pdo->exec($sql);
                    } catch (Exception $exception) {
                        if ($db_type !== 'pgsql' || !str_contains($exception->getMessage(), 'already exists')) {
                            $xml .= '<db_created>0</db_created>';
                            $xml .= '<alert>' . pdo_error() . '</alert>';
                            $db_created = false;
                        }
                    }

                    if ($db_created) {
                        Artisan::call('migrate --force');

                        // If we are with PostGreSQL we need to add some extra functions
                        if ($db_type == 'pgsql') {
                            $sqlfile = $config->get('CDASH_ROOT_DIR') . '/sql/pgsql/cdash.ext.sql';

                            // Create the language. PgSQL has no way to know if the language already
                            // exists
                            @pdo_query('CREATE LANGUAGE plpgsql');

                            $file_content = file($sqlfile);
                            $query = '';
                            foreach ($file_content as $sql_line) {
                                $tsl = trim($sql_line);
                                if (($sql_line != '') && (substr($tsl, 0, 2) != '--')) {
                                    $query .= $sql_line;
                                    $possemicolon = strrpos($query, ';');
                                    if ($possemicolon !== false && substr_count($query, '\'', 0, $possemicolon) % 2 == 0) {
                                        // We need to remove only the last semicolon
                                        $pos = strrpos($query, ';');
                                        if ($pos !== false) {
                                            $query = substr($query, 0, $pos) . substr($query, $pos + 1);
                                        }
                                        $result = pdo_query($query);
                                        if (!$result) {
                                            $xml .= '<db_created>0</db_created>';
                                            die(pdo_error());
                                        }
                                        $query = '';
                                    }
                                }
                            }

                            // Check the version of PostgreSQL
                            $result_version = pdo_query('SELECT version()');
                            $version_array = pdo_fetch_array($result_version);
                            if (strpos(strtolower($version_array[0]), 'postgresql 9.') !== false) {
                                // For PgSQL 9.0 we need to set the bytea_output to 'escape' (it was changed to hexa)
                                @pdo_query("ALTER DATABASE {$db_name} SET bytea_output TO 'escape'");
                            }
                        }

                        $passwordHash = User::PasswordHash($admin_password);
                        if ($passwordHash === false) {
                            $xml .= '<alert>Failed to hash password</alert>';
                        } else {
                            $user = new \CDash\Model\User();
                            $user->Email = $admin_email;
                            $user->Password = $passwordHash;
                            $user->FirstName = 'administrator';
                            $user->Institution = 'Kitware Inc.';
                            $user->Admin = 1;
                            $user->Save();
                        }
                        $xml .= '<db_created>1</db_created>';

                        // Set the database version
                        setVersion();
                    }
                }
            }
        }

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/install', true),
            'title' => 'Installation'
        ]);
    }

    public function monitor(): View
    {
        $user = Auth::user();
        if ($user->admin) {
            $content = $this->monitor_currently_processing_submissions();
            $content .= $this->monitor_pending_submissions();
            $content .= $this->monitor_average_wait_times();
            $content .= $this->monitor_submissionprocessor_table();
            $content .= $this->monitor_submission_table();
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => $content,
                'title' => 'System Monitor'
            ]);
        } else {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Admin login required to display monitoring info.',
                'title' => 'System Monitor'
            ]);
        }
    }

    // TODO: (williamjallen) Convert this function into a Blade template and move extra logic to monitor()
    private function monitor_currently_processing_submissions(): string
    {
        $db = Database::getInstance();

        if (config('database.default') == 'pgsql') {
            $sql_query = "SELECT now() AT TIME ZONE 'UTC'";
        } else {
            $sql_query = 'SELECT UTC_TIMESTAMP()';
        }
        $current_time = $db->executePreparedSingleRow($sql_query);

        $sql_query = 'SELECT project.name, submission.*, ';
        if (config('database.default') == 'pgsql') {
            $sql_query .= 'round((extract(EPOCH FROM now() - created)/3600)::numeric, 2) AS hours_ago ';
        } else {
            $sql_query .= 'ROUND(TIMESTAMPDIFF(SECOND, created, UTC_TIMESTAMP)/3600, 2) AS hours_ago ';
        }

        $sql_query .= 'FROM project, submission WHERE project.id = submission.projectid AND status = 1';
        $rows = $db->executePrepared($sql_query);

        $sep = ', ';

        $html = '<h1>Currently Processing Submissions as of ' . $current_time[0] . ' UTC</h1>';
        $html .= '<pre>';
        if (count($rows) > 0) {
            $html .= 'project name, backlog in hours' . "\n";
            $html .= '    submission.id, filename, projectid, status, attempts, filesize, filemd5sum, lastupdated, created, started, finished' . "\n";
            $html .= "\n";
            foreach ($rows as $row) {
                $html .= $row['name'] . $sep . $row['hours_ago'] . ' hours behind' . "\n";
                $html .= '    ' . $row['id'] .
                    $sep . $row['filename'] .
                    $sep . $row['projectid'] .
                    $sep . $row['status'] .
                    $sep . $row['attempts'] .
                    $sep . $row['filesize'] .
                    $sep . $row['filemd5sum'] .
                    $sep . $row['lastupdated'] .
                    $sep . $row['created'] .
                    $sep . $row['started'] .
                    $sep . $row['finished'] .
                    "\n";
                $html .= "\n";
            }
        } else {
            $html .= 'Nothing is currently processing...' . "\n";
        }
        $html .= '</pre>';
        $html .= '<br/>';

        return $html;
    }

    // TODO: (williamjallen) Convert this function into a Blade template and move extra logic to monitor()
    private function monitor_pending_submissions(): string
    {
        $db = Database::getInstance();
        $rows = $db->executePrepared('
                SELECT project.name, project.id, COUNT(submission.id) AS c
                FROM project, submission
                WHERE
                    project.id = submission.projectid
                    AND status = 0
                GROUP BY project.name, project.id
            ');

        $sep = ', ';

        $html = '<h1>Pending Submissions</h1>';
        $html .= '<pre>';
        if (count($rows) > 0) {
            $html .= 'project.name, project.id, count of pending queued submissions' . "\n";
            $html .= "\n";
            foreach ($rows as $row) {
                $html .= $row['name'] .
                    $sep . $row['id'] .
                    $sep . $row['c'] .
                    "\n";
            }
        } else {
            $html .= 'Nothing queued...' . "\n";
        }
        $html .= '</pre>';
        $html .= '<br/>';

        return $html;
    }

    // TODO: (williamjallen) Convert this function into a Blade template and move extra logic to monitor()
    private function monitor_average_wait_time(int $projectid): string
    {
        $project_name = get_project_name($projectid);
        if (config('database.default') == 'pgsql') {
            $sql_query = "SELECT extract(EPOCH FROM now() - created)/3600 as hours_ago,
            current_time AS time_local,
            count(created) AS num_files,
            round(avg((extract(EPOCH FROM started - created)/3600)::numeric), 1) AS avg_hours_delay,
            avg(extract(EPOCH FROM finished - started)) AS mean,
            min(extract(EPOCH FROM finished - started)) AS shortest,
            max(extract(EPOCH FROM finished - started)) AS longest
            FROM submission WHERE status = 2 AND projectid = ?
            GROUP BY hours_ago ORDER BY hours_ago ASC LIMIT 48";
        } else {
            $sql_query = "SELECT TIMESTAMPDIFF(HOUR, created, UTC_TIMESTAMP) as hours_ago,
            TIME_FORMAT(CONVERT_TZ(created, '+00:00', 'SYSTEM'), '%l:00 %p') AS time_local,
            COUNT(created) AS num_files,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, created, started))/3600, 1) AS avg_hours_delay,
            AVG(TIMESTAMPDIFF(SECOND, started, finished)) AS mean,
            MIN(TIMESTAMPDIFF(SECOND, started, finished)) AS shortest,
            MAX(TIMESTAMPDIFF(SECOND, started, finished)) AS longest
            FROM submission WHERE status = 2 AND projectid = ?
            GROUP BY hours_ago ORDER BY hours_ago ASC LIMIT 48";
        }

        $db = Database::getInstance();
        $rows = $db->executePrepared($sql_query, [intval($projectid)]);

        $html = '';
        if (count($rows) > 0) {
            $html .= "<h2>Wait times for $project_name</h2>\n";
            $html .= "<table border=1>\n";
            $html .= "<tr>\n";
            $html .= "<th>Hours Ago</th>\n";
            $html .= "<th>Local Time</th>\n";
            $html .= "<th>Files Processed Successfully</th>\n";
            $html .= "<th>Avg Hours Spent Queued Before Processing</th>\n";
            $html .= "<th>Avg Seconds Spent Processing a File</th>\n";
            $html .= "<th>Min Seconds Spent Processing a File</th>\n";
            $html .= "<th>Max Seconds Spent Processing a File</th>\n";
            $html .= "</tr>\n";
            foreach ($rows as $row) {
                $html .= "<tr>\n";
                $html .= "<td style='text-align:center'>{$row['hours_ago']}</td>\n";
                $html .= "<td style='text-align:center'>{$row['time_local']}</td>\n";
                $html .= "<td style='text-align:center'>{$row['num_files']}</td>\n";
                $html .= "<td style='text-align:center'>{$row['avg_hours_delay']}</td>\n";
                $html .= "<td style='text-align:center'>{$row['mean']}</td>\n";
                $html .= "<td style='text-align:center'>{$row['shortest']}</td>\n";
                $html .= "<td style='text-align:center'>{$row['longest']}</td>\n";
                $html .= "</tr>\n";
            }
            $html .= "</table>\n";
        } else {
            $html .= "<h2>No average wait time data for $project_name</h2>\n";
        }

        return $html;
    }

    // TODO: (williamjallen) Convert this function into a Blade template and move extra logic to monitor()
    private function monitor_average_wait_times(): string
    {
        $db = Database::getInstance();
        $rows = $db->executePrepared('
                SELECT projectid, COUNT(*) AS c
                FROM submission
                WHERE status=2
                GROUP BY projectid
            ');

        $html = '<h1>Average Wait Times per Project</h1>';
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                if ($row['c'] > 0) {
                    $this->monitor_average_wait_time(intval($row['projectid']));
                }
            }
        } else {
            $html .= 'No finished submissions for average wait time measurement...' . "\n";
        }
        $html .= '<br/>';

        return $html;
    }

    // TODO: (williamjallen) Convert this function into a Blade template and move extra logic to monitor()
    private function monitor_submissionprocessor_table(): string
    {
        $db = Database::getInstance();
        $rows = $db->executePrepared('
                SELECT project.name, submissionprocessor.*
                FROM project, submissionprocessor
                WHERE project.id = submissionprocessor.projectid
            ');

        $html = '<h1>Table `submissionprocessor` (one row per project)</h1>';
        $html .= "<table border=1>\n";
        $html .= "<tr>\n";
        $html .= "<th>Project Name</th>\n";
        $html .= "<th>Project ID</th>\n";
        $html .= "<th>Process ID</th>\n";
        $html .= "<th>Last Updated</th>\n";
        $html .= "<th>Locked</th>\n";
        $html .= "</tr>\n";
        foreach ($rows as $row) {
            $html .= "<tr>\n";
            $html .= "<td style='text-align:center'>{$row['name']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['projectid']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['pid']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['lastupdated']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['locked']}</td>\n";
            $html .= "</tr>\n";
        }
        $html .= "</table>\n";
        $html .= '<br/>';

        return $html;
    }

    // TODO: (williamjallen) Convert this function into a Blade template and move extra logic to monitor()
    private function monitor_submission_table(): string
    {
        @$limit = $_REQUEST['limit'];
        if (!isset($limit)) {
            $limit = 25;
        } else {
            $limit = intval($limit);
        }

        $html = "<h1>Table `submission` (most recently queued $limit)</h1>";
        $html .= "<table border=1>\n";
        $html .= "<tr>\n";
        $html .= "<th>id</th>\n";
        $html .= "<th>filename</th>\n";
        $html .= "<th>projectid</th>\n";
        $html .= "<th>status</th>\n";
        $html .= "<th>attempts</th>\n";
        $html .= "<th>filesize</th>\n";
        $html .= "<th>filemd5sum</th>\n";
        $html .= "<th>lastupdated</th>\n";
        $html .= "<th>created</th>\n";
        $html .= "<th>started</th>\n";
        $html .= "<th>finished</th>\n";
        $html .= "</tr>\n";

        $db = Database::getInstance();
        $rows = $db->executePrepared('SELECT * FROM submission ORDER BY id DESC LIMIT ?', [$limit]);


        foreach ($rows as $row) {
            $html .= "<tr>\n";
            $html .= "<td style='text-align:center'>{$row['id']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['filename']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['projectid']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['status']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['attempts']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['filesize']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['filemd5sum']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['lastupdated']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['created']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['started']}</td>\n";
            $html .= "<td style='text-align:center'>{$row['finished']}</td>\n";
            $html .= "</tr>\n";
        }
        $html .= "</table>\n";
        $html .= "<br/>\n";

        return $html;
    }

    public function gitinfo(): View
    {
        $user = Auth::user();
        if ($user->admin) {
            $content = $this->gitinfo_git_output('--version');
            $content .= $this->gitinfo_git_output('remote -v');
            $content .= $this->gitinfo_git_output('status');
            $content .= $this->gitinfo_git_output('diff');

            $config = Config::getInstance();
            $content .= $this->gitinfo_file_contents($config->get('CDASH_ROOT_DIR') . '../../.env');
            $content .= $this->gitinfo_file_contents($config->get('CDASH_ROOT_DIR') . '/tests/config.test.local.php');
            $content .= '<br/>';

            return view('cdash', [
                'xsl' => true,
                'xsl_content' => $content,
                'title' => 'Git Information'
            ]);
        } else {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Admin login required to display git info.',
                'title' => 'Git Information'
            ]);
        }
    }

    private function gitinfo_git_output(string $cmd): string
    {
        // Assumes being able to run 'git' on the web server in the CDash
        // directory...
        //
        $git_output = `git $cmd`;

        $html = '<h3>git ' . $cmd . '</h3>';
        $html .= '<pre>';
        $html .= htmlentities($git_output);
        $html .= '</pre>';
        $html .= '<br/>';

        return $html;
    }

    private function gitinfo_file_contents(string $filename): string
    {
        $html = '';
        // Emit the contents of the named file, but only if it exists.
        // If it doesn't exist, emit nothing.
        //
        if (file_exists($filename)) {
            $contents = file_get_contents($filename);

            $html .= '<h3>contents of "' . $filename . '"</h3>';
            $html .= '<pre>';
            $html .= htmlentities($contents);
            $html .= '</pre>';
            $html .= '<br/>';
        }

        return $html;
    }
}
