<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Validators\Password;
use CDash\Config;
use CDash\Database;
use CDash\Model\BuildUpdate;
use CDash\Model\SubProject;
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
require_once 'include/version.php';
require_once 'include/upgrade_functions.php';

final class AdminController extends AbstractController
{
    public function removeBuilds(): View|RedirectResponse
    {
        $config = Config::getInstance();

        @set_time_limit(0);

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
            $this::setVersion();

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
            $this::setVersion();

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
            $this::setVersion();

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
            $this::setVersion();

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
            $this::setVersion();

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

    /**
     * Set the CDash version number in the database
     */
    public static function setVersion(): string
    {
        $config = Config::getInstance();

        $major = $config->get('CDASH_VERSION_MAJOR');
        $minor = $config->get('CDASH_VERSION_MINOR');
        $patch = $config->get('CDASH_VERSION_PATCH');

        $stmt = DB::select('SELECT major FROM version');
        $version = [$major, $minor, $patch];

        if (count($stmt) === 0) {
            DB::insert('INSERT INTO version (major, minor, patch) VALUES (?, ?, ?)', $version);
        } else {
            DB::update('UPDATE version SET major=?, minor=?, patch=?', $version);
        }

        return "$major.$minor.$patch";
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
                        $this::setVersion();
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

    public function userStatistics(): \Illuminate\Http\Response
    {
        return response()->angular_view('userStatistics');
    }
}
