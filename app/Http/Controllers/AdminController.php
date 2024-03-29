<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Validators\Password;
use CDash\Config;
use CDash\Model\Project;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PDO;

require_once 'include/api_common.php';
require_once 'include/ctestparser.php';
require_once 'include/upgrade_functions.php';

final class AdminController extends AbstractController
{
    public function removeBuilds(): View|RedirectResponse
    {
        @set_time_limit(0);

        $projectid = intval($_GET['projectid'] ?? 0);

        $alert = '';

        //get date info here
        @$dayTo = intval($_POST['dayFrom']);
        if (empty($dayTo)) {
            $time = strtotime('2000-01-01 00:00:00');

            if ($projectid > 0) {
                // find the first and last builds
                $starttime = DB::select('
                    SELECT starttime
                    FROM build
                    WHERE projectid=?
                    ORDER BY starttime ASC
                    LIMIT 1
                ', [$projectid]);
                if (count($starttime) === 1) {
                    $time = strtotime($starttime[0]->starttime);
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

        // List the available projects
        $available_projects = [];
        $projects = DB::select('SELECT id, name FROM project');
        foreach ($projects as $projects_array) {
            $available_project = new Project();
            $available_project->Id = (int) $projects_array->id;
            $available_project->Name = $projects_array->name;
            $available_projects[] = $available_project;
        }

        // Delete the builds
        if (isset($_POST['Submit'])) {
            if (config('database.default') === 'pgsql') {
                $timestamp_sql =  "CAST(CONCAT(?, '-', ?, '-', ?, ' 00:00:00') AS timestamp)";
            } else {
                $timestamp_sql =  "TIMESTAMP(CONCAT(?, '-', ?, '-', ?, ' 00:00:00'))";
            }

            $build = DB::select("
                         SELECT id
                         FROM build
                         WHERE
                             projectid = ?
                             AND parentid IN (0, -1)
                             AND starttime <= $timestamp_sql
                             AND starttime >= $timestamp_sql
                         ORDER BY starttime ASC
                     ", [
                $projectid,
                $yearTo,
                $monthTo,
                $dayTo,
                $yearFrom,
                $monthFrom,
                $dayFrom,
            ]);

            $builds = [];
            foreach ($build as $build_array) {
                $builds[] = (int) $build_array->id;
            }

            remove_build_chunked($builds);
            $alert = 'Removed ' . count($builds) . ' builds.';
        }

        return view('admin.remove-builds')
            ->with('alert', $alert)
            ->with('selected_projectid', $projectid)
            ->with('available_projects', $available_projects)
            ->with('monthFrom', $monthFrom)
            ->with('dayFrom', $dayFrom)
            ->with('yearFrom', $yearFrom)
            ->with('monthTo', $monthTo)
            ->with('dayTo', $dayTo)
            ->with('yearTo', $yearTo);
    }

    public function upgrade()
    {
        $config = Config::getInstance();

        @set_time_limit(0);

        $xml = begin_XML_for_XSLT();
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Maintenance</menusubtitle>';

        @$AssignBuildToDefaultGroups = $_POST['AssignBuildToDefaultGroups'];
        @$FixBuildBasedOnRule = $_POST['FixBuildBasedOnRule'];
        @$DeleteBuildsWrongDate = $_POST['DeleteBuildsWrongDate'];
        @$CheckBuildsWrongDate = $_POST['CheckBuildsWrongDate'];
        @$ComputeTestTiming = $_POST['ComputeTestTiming'];
        @$ComputeUpdateStatistics = $_POST['ComputeUpdateStatistics'];

        @$Cleanup = $_POST['Cleanup'];
        @$Dependencies = $_POST['Dependencies'];
        @$Audit = $_POST['Audit'];
        @$ClearAudit = $_POST['Clear'];

        $configFile = $config->get('CDASH_ROOT_DIR') . "/AuditReport.log";

        // Compute the testtime
        if ($ComputeTestTiming) {
            $TestTimingDays = (int) ($_POST['TestTimingDays'] ?? 0);
            if ($TestTimingDays > 0) {
                ComputeTestTiming($TestTimingDays);
                $xml .= add_XML_value('alert', 'Timing for tests has been computed successfully.');
            } else {
                $xml .= add_XML_value('alert', 'Wrong number of days.');
            }
        }

        // Compute the user statistics
        if ($ComputeUpdateStatistics) {
            $UpdateStatisticsDays = (int) ($_POST['UpdateStatisticsDays'] ?? 0);
            if ($UpdateStatisticsDays > 0) {
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

        if ($AssignBuildToDefaultGroups) {
            // Loop throught the builds
            $builds = pdo_query('SELECT id,type,projectid FROM build WHERE id NOT IN (SELECT buildid as id FROM build2group)');

            while ($build_array = pdo_fetch_array($builds)) {
                $buildid = $build_array['id'];
                $buildtype = $build_array['type'];
                $projectid = $build_array['projectid'];

                $buildgroup_array = pdo_fetch_array(pdo_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'"));

                $groupid = $buildgroup_array['id'];
                DB::insert("INSERT INTO build2group(buildid,groupid) VALUES ('$buildid','$groupid')");
            }

            $xml .= add_XML_value('alert', 'Builds have been added to default groups successfully.');
        }

        $xml .= '</cdash>';

        return $this->view('cdash', 'Maintenance')
            ->with('xsl', true)
            ->with('xsl_content', generate_XSLT($xml, base_path() . '/app/cdash/public/upgrade', true));
    }

    public function install(): View
    {
        @set_time_limit(0);

        // This is the installation script for CDash
        if (class_exists('XsltProcessor') === false) {
            return $this->view('cdash', 'Installation')
                ->with('xsl', true)
                ->with('xsl_content', '<font color="#FF0000">Your PHP installation does not support XSL. Please install the XSL extension.</font>');
        }

        $config = Config::getInstance();

        if (config('app.env') === 'production') {
            return $this->view('cdash', 'Installation')
                ->with('xsl', true)
                ->with('xsl_content', 'CDash is in production mode. Install cannot be accessed. Set APP_ENV=development in your .env file if you want to access the installation.');
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
        $xml .= '<connectiondb_name>' . $db_name . '</connectiondb_name>';

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
        if (!is_writable(Storage::path('upload'))) {
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
                                if ($sql_line !== '' && !str_starts_with($tsl, '--')) {
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
                                            abort(500, pdo_error());
                                        }
                                        $query = '';
                                    }
                                }
                            }

                            // Check the version of PostgreSQL
                            $result_version = pdo_query('SELECT version()');
                            $version_array = pdo_fetch_array($result_version);
                            if (str_contains(strtolower($version_array[0]), 'postgresql 9.')) {
                                // For PgSQL 9.0 we need to set the bytea_output to 'escape' (it was changed to hexa)
                                @pdo_query("ALTER DATABASE {$db_name} SET bytea_output TO 'escape'");
                            }
                        }

                        $user = new \CDash\Model\User();
                        $user->Email = $admin_email;
                        $user->Password = password_hash($admin_password, PASSWORD_DEFAULT);
                        $user->FirstName = 'administrator';
                        $user->Institution = 'Kitware Inc.';
                        $user->Admin = 1;
                        $user->Save();
                        $xml .= '<db_created>1</db_created>';
                    }
                }
            }
        }

        $xml .= '</cdash>';

        return $this->view('cdash', 'Installation')
            ->with('xsl', true)
            ->with('xsl_content', generate_XSLT($xml, base_path() . '/app/cdash/public/install', true));
    }

    public function userStatistics(): \Illuminate\Http\Response
    {
        return response()->angular_view('userStatistics');
    }
}
