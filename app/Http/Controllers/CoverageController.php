<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProjectPermissions;
use App\Services\TestingDay;
use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFile2User;
use CDash\Model\CoverageFileLog;
use CDash\Model\CoverageSummary;
use CDash\Model\Project;
use CDash\Model\UserProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

require_once 'include/filterdataFunctions.php';

class CoverageController extends AbstractController
{
    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from manageCoverage.php and have been copied (almost) as-is.
     */
    public function manageCoverage(): View|RedirectResponse
    {
        $userid = Auth::id();
        // Checks
        if (!isset($userid) || !is_numeric($userid)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Not a valid userid!'
            ]);
        }

        $xml = begin_XML_for_XSLT();
        $xml .= '<backurl>user.php</backurl>';
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Coverage</menusubtitle>';

        @$projectid = $_GET['projectid'];
        if ($projectid != null) {
            $projectid = intval($projectid);
        }

        $Project = new Project;

        $buildid = 0;
        if (isset($_GET['buildid'])) {
            $buildid = intval($_GET['buildid']);
        }

        // If the projectid is not set and there is only one project we go directly to the page
        if (!isset($projectid)) {
            $projectids = $Project->GetIds();
            if (count($projectids) == 1) {
                $projectid = $projectids[0];
            }
        }
        $projectid = intval($projectid);

        /** @var User $User */
        $User = Auth::user();
        $Project->Id = $projectid;
        if (!Gate::allows('edit-project', $Project)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "You don't have the permissions to access this page"
            ]);
        }

        $sql = 'SELECT id,name FROM project';
        $params = [];
        if ($User->IsAdmin() == false) {
            $sql .= ' WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid=? AND role>0)';
            $params[] = intval($userid);
        }

        $db = Database::getInstance();
        $project = $db->executePrepared($sql, $params);
        foreach ($project as $project_array) {
            $xml .= '<availableproject>';
            $xml .= add_XML_value('id', $project_array['id']);
            $xml .= add_XML_value('name', $project_array['name']);
            if ($project_array['id'] == $projectid) {
                $xml .= add_XML_value('selected', '1');
            }
            $xml .= '</availableproject>';
        }

        // Display the current builds who have coverage for the past 7 days
        $currentUTCTime = gmdate(FMT_DATETIME);
        $beginUTCTime = gmdate(FMT_DATETIME, time() - 3600 * 7 * 24); // 7 days

        $CoverageFile2User = new CoverageFile2User();
        $CoverageFile2User->ProjectId = $projectid;

        // Change the priority of selected files
        if (isset($_POST['changePrioritySelected'])) {
            foreach ($_POST['selectionFiles'] as $key => $value) {
                $CoverageFile2User->FullPath = htmlspecialchars($value);
                $CoverageFile2User->SetPriority(intval($_POST['prioritySelectedSelection']));
            }
        }

        // Remove the selected authors
        if (isset($_POST['removeAuthorsSelected'])) {
            foreach ($_POST['selectionFiles'] as $key => $value) {
                $CoverageFile2User->FullPath = htmlspecialchars($value);
                $CoverageFile2User->RemoveAuthors();
            }
        }

        // Add the selected authors
        if (isset($_POST['addAuthorsSelected'])) {
            foreach ($_POST['selectionFiles'] as $key => $value) {
                $CoverageFile2User->UserId = intval($_POST['userSelectedSelection']);
                $CoverageFile2User->FullPath = htmlspecialchars($value);
                $CoverageFile2User->Insert();
            }
        }

        // Add an author manually
        if (isset($_POST['addAuthor'])) {
            $CoverageFile2User->UserId = intval($_POST['userSelection']);
            $CoverageFile2User->FullPath = htmlspecialchars($_POST['fullpath']);
            $CoverageFile2User->Insert();
        }

        // Remove an author manually
        if (isset($_GET['removefileid'])) {
            $CoverageFile2User->UserId = intval($_GET['removeuserid']);
            $CoverageFile2User->FileId = intval($_GET['removefileid']);
            $CoverageFile2User->Remove();
        }

        // Assign last author
        if (isset($_POST['assignLastAuthor'])) {
            $CoverageFile2User->AssignLastAuthor(intval($buildid));
        }

        // Assign all authors
        if (isset($_POST['assignAllAuthors'])) {
            $CoverageFile2User->AssignAllAuthors(intval($buildid));
        }

        // Upload file
        if (isset($_POST['uploadAuthorsFile'])) {
            $contents = file_get_contents($_FILES['authorsFile']['tmp_name']);
            if (strlen($contents) > 0) {
                $pos = 0;
                $pos2 = strpos($contents, "\n");
                while ($pos !== false) {
                    $line = substr($contents, $pos, $pos2 - $pos);

                    $file = '';
                    $authors = array();

                    // first is the svnuser
                    $posfile = strpos($line, ':');
                    if ($posfile !== false) {
                        $file = trim(substr($line, 0, $posfile));
                        $begauthor = $posfile + 1;
                        $endauthor = strpos($line, ',', $begauthor);
                        while ($endauthor !== false) {
                            $authors[] = trim(substr($line, $begauthor, $endauthor - $begauthor));
                            $begauthor = $endauthor + 1;
                            $endauthor = strpos($line, ',', $begauthor);
                        }

                        $authors[] = trim(substr($line, $begauthor));

                        // Insert the user
                        $CoverageFile = new CoverageFile;
                        if ($CoverageFile->GetIdFromName($file, $buildid) === false) {
                            $xml .= add_XML_value('warning', '*File not found for: ' . $file);
                        } else {
                            foreach ($authors as $author) {
                                $User = new User;
                                $CoverageFile2User->UserId = $User->GetIdFromName($author);
                                if ($CoverageFile2User->UserId === false) {
                                    $xml .= add_XML_value('warning', '*User not found for: ' . $author);
                                } else {
                                    $CoverageFile2User->FullPath = $file;
                                    $CoverageFile2User->Insert();
                                }
                            }
                        }
                    }

                    $pos = $pos2;
                    $pos2 = strpos($contents, "\n", $pos2 + 1);
                }
            }
        }

        // Send an email
        if (isset($_POST['sendEmail'])) {
            $coverageThreshold = $Project->GetCoverageThreshold();
            $userids = $CoverageFile2User->GetUsersFromProject();
            foreach ($userids as $userid) {
                $CoverageFile2User->UserId = $userid;
                $fileids = $CoverageFile2User->GetFiles();

                $files = [];

                // For each file check the coverage metric
                foreach ($fileids as $fileid) {
                    $coveragefile = new CoverageFile;
                    $CoverageFile2User->FileId = $fileid;
                    $coveragefile->Id = $CoverageFile2User->GetCoverageFileId($buildid);
                    $metric = $coveragefile->GetMetric();
                    if ($metric < ($coverageThreshold / 100.0)) {
                        $file = [
                            'percent' => $coveragefile->GetLastPercentCoverage(),
                            'path' => $coveragefile->GetPath(),
                            'id' => $fileid,
                        ];
                        $files[] = $file;
                    }
                }

                // Send an email if the number of uncovered file is greater than one
                if (count($files) > 0) {
                    // Writing the message
                    $messagePlainText = 'The following files for the project ' . $Project->GetName();
                    $messagePlainText .= ' have a low coverage and ';
                    $messagePlainText .= "you have been identified as one of the authors of these files.\n";

                    foreach ($files as $file) {
                        $messagePlainText .= $file['path'] . ' (' . round($file['percent'], 2) . "%)\n";
                    }

                    $messagePlainText .= 'Details on the submission can be found at ';

                    $config = Config::getInstance();
                    $messagePlainText .= $config->getBaseUrl();
                    $messagePlainText .= "\n\n";
                    $serverName = $config->get('CDASH_SERVER_NAME');
                    if (strlen($serverName) == 0) {
                        $serverName = $_SERVER['SERVER_NAME'];
                    }

                    $messagePlainText .= "\n-CDash on " . $serverName . "\n";

                    // Send the email
                    $title = 'CDash [' . $Project->GetName() . '] - Low Coverage';

                    $user = User::find($userid);
                    cdashmail($user->email, $title, $messagePlainText);
                    $xml .= add_XML_value('warning', '*The email has been sent successfully.');
                } else {
                    $xml .= add_XML_value('warning', '*No email sent because the coverage is green.');
                }
            }
        }

        // If we change the priority
        if (isset($_POST['prioritySelection'])) {
            $CoverageFile2User = new CoverageFile2User();
            $CoverageFile2User->ProjectId = $projectid;
            $CoverageFile2User->FullPath = htmlspecialchars($_POST['fullpath']);
            $CoverageFile2User->SetPriority(intval($_POST['prioritySelection']));
        }

        /* We start generating the XML here */

        // Find the recent builds for this project
        if ($projectid > 0) {
            $xml .= '<project>';
            $xml .= add_XML_value('id', $Project->Id);
            $xml .= add_XML_value('name', $Project->GetName());
            $xml .= add_XML_value('name_encoded', urlencode($Project->GetName()));

            if ($buildid > 0) {
                $xml .= add_XML_value('buildid', $buildid);
            }

            $CoverageSummary = new CoverageSummary();

            $buildids = $CoverageSummary->GetBuilds($Project->Id, $beginUTCTime, $currentUTCTime);
            rsort($buildids);
            foreach ($buildids as $buildId) {
                $Build = new Build();
                $Build->Id = $buildId;
                $Build->FillFromId($Build->Id);
                $xml .= '<build>';
                $xml .= add_XML_value('id', $buildId);
                $xml .= add_XML_value('name', $Build->GetSite()->name . '-' . $Build->GetName() . ' [' . gmdate(FMT_DATETIME, strtotime($Build->StartTime)) . ']');
                if ($buildid > 0 && $buildId == $buildid) {
                    $xml .= add_XML_value('selected', 1);
                }
                $xml .= '</build>';
            }

            // For now take the first one
            if ($buildid > 0) {
                // Find the files associated with the build
                $Coverage = new Coverage();
                $Coverage->BuildId = $buildid;
                $fileIds = $Coverage->GetFiles();
                $row = '0';
                sort($fileIds);
                foreach ($fileIds as $fileid) {
                    $CoverageFile = new CoverageFile();
                    $CoverageFile->Id = $fileid;
                    $xml .= '<file>';
                    $CoverageFile2User->FullPath = $CoverageFile->GetPath();

                    $xml .= add_XML_value('fullpath', $CoverageFile->GetPath());
                    $xml .= add_XML_value('id', $CoverageFile2User->GetId());
                    $xml .= add_XML_value('fileid', $fileid);

                    $row = $row == 0 ? 1 : 0;

                    $xml .= add_XML_value('row', $row);

                    // Get the authors
                    $CoverageFile2User->FullPath = $CoverageFile->GetPath();
                    $authorids = $CoverageFile2User->GetAuthors();
                    foreach ($authorids as $authorid) {
                        $xml .= '<author>';
                        $user = User::find($authorid);
                        $xml .= add_XML_value('name', $user->full_name);
                        $xml .= add_XML_value('id', $authorid);
                        $xml .= '</author>';
                    }

                    $priority = $CoverageFile2User->GetPriority();
                    if ($priority > 0) {
                        $xml .= add_XML_value('priority', $priority);
                    }

                    $xml .= '</file>';
                }
            }

            // List all the users of the project
            $UserProject = new UserProject();
            $UserProject->ProjectId = $Project->Id;
            $userIds = $UserProject->GetUsers();
            foreach ($userIds as $userid) {
                $User = User::find($userid);
                $xml .= '<user>';
                $xml .= add_XML_value('id', $userid);
                $xml .= add_XML_value('name', $User->full_name);
                $xml .= '</user>';
            }

            $xml .= '</project>';
        }
        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/manageCoverage', true),
            'project' => $Project,
            'title' => 'Manage Coverage'
        ]);
    }

    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from viewCoverage.php and have been copied (almost) as-is.
     */
    public function viewCoverage(): View|RedirectResponse
    {
        @set_time_limit(0);

        @$buildid = $_GET['buildid'];
        if ($buildid != null) {
            $buildid = intval($buildid);
        }

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars($date);
        }

        if (isset($_GET['value1']) && strlen($_GET['value1']) > 0) {
            $filtercount = $_GET['filtercount'];
        } else {
            $filtercount = 0;
        }

        // Checks
        if (!isset($buildid) || !is_numeric($buildid)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Not a valid buildid!'
            ]);
        }

        @$userid = Auth::id();
        if (!isset($userid)) {
            $userid = 0;
        }

        $db = Database::getInstance();


        $build_array = $db->executePreparedSingleRow('
                   SELECT
                       b.starttime,
                       b.projectid,
                       b.siteid,
                       b.type,
                       b.name,
                       sp.groupid
                   FROM build AS b
                   LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
                   LEFT JOIN subproject AS sp ON (sp2b.subprojectid = sp.id)
                   WHERE b.id=?
               ', [intval($buildid)]);
        $projectid = $build_array['projectid'];

        if (!isset($projectid) || $projectid == 0 || !is_numeric($projectid)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "This project doesn't exist. Maybe it has been deleted."
            ]);
        }

        $policy = checkUserPolicy($projectid);
        if ($policy !== true) {
            return $policy;
        }

        $project = new Project();
        $project->Id = $projectid;
        $project->Fill();
        if (!$project->Exists()) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "This project doesn't exist."
            ]);
        }

        $role = 0;
        $user2project = $db->executePreparedSingleRow('
                    SELECT role
                    FROM user2project
                    WHERE userid=? AND projectid=?
                ', [intval($userid), intval($projectid)]);
        if (!empty($user2project)) {
            $role = $user2project['role'];
        }

        $projectshowcoveragecode = 1;
        if (!$project->ShowCoverageCode && $role < 2) {
            $projectshowcoveragecode = 0;
        }

        $xml = begin_XML_for_XSLT();
        $xml .= get_cdash_dashboard_xml_by_name($project->Name, $date);
        $xml .= '<buildid>' . $buildid . '</buildid>';

        $threshold = $project->CoverageThreshold;
        if ($build_array['groupid'] > 0) {
            $row = $db->executePreparedSingleRow('
               SELECT coveragethreshold
               FROM subprojectgroup
               WHERE projectid=? AND id=?
           ', [intval($projectid), intval($build_array['groupid'])]);
            if (!empty($row) && isset($row['coveragethreshold'])) {
                $threshold = intval($row['coveragethreshold']);
            }
        }

        $date = TestingDay::get($project, $build_array['starttime']);
        $xml .= '<menu>';
        $xml .= add_XML_value('back', 'index.php?project=' . urlencode($project->Name) . "&date=$date");

        $build = new Build();
        $build->Id = $buildid;
        $previous_buildid = $build->GetPreviousBuildId();
        $current_buildid = $build->GetCurrentBuildId();
        $next_buildid = $build->GetNextBuildId();

        if ($previous_buildid > 0) {
            $xml .= add_XML_value('previous', 'viewCoverage.php?buildid=' . $previous_buildid);
        } else {
            $xml .= add_XML_value('noprevious', '1');
        }

        $xml .= add_XML_value('current', "viewCoverage.php?buildid=$current_buildid");

        if ($next_buildid > 0) {
            $xml .= add_XML_value('next', "viewCoverage.php?buildid=$next_buildid");
        } else {
            $xml .= add_XML_value('nonext', '1');
        }
        $xml .= '</menu>';

        $xml .= add_XML_value('filtercount', $filtercount);
        if ($filtercount > 0) {
            $xml .= add_XML_value('showfilters', 1);
        }

        // coverage
        $xml .= '<coverage>';
        $coverage_array = $db->executePreparedSingleRow('
                      SELECT
                          sum(loctested) as loctested,
                          sum(locuntested) as locuntested,
                          sum(branchstested) as branchstested,
                          sum(branchsuntested) as branchsuntested
                      FROM coverage
                      WHERE buildid=?
                      GROUP BY buildid
                  ', [intval($buildid)]);

        $xml .= add_XML_value('starttime', date('l, F d Y', strtotime($build_array['starttime'])));
        $xml .= add_XML_value('loctested', $coverage_array['loctested']);
        $xml .= add_XML_value('locuntested', $coverage_array['locuntested']);

        $xml .= add_XML_value('branchstested', $coverage_array['branchstested']);
        $xml .= add_XML_value('branchsuntested', $coverage_array['branchsuntested']);
        $percentcoverage = compute_percentcoverage(
            $coverage_array['loctested'], $coverage_array['locuntested']);

        $xml .= add_XML_value('loc', $coverage_array['loctested'] + $coverage_array['locuntested']);
        $xml .= add_XML_value('percentcoverage', $percentcoverage);
        $xml .= add_XML_value('percentagegreen', $threshold);
        // Above this number of the coverage is green
        $metricpass = $threshold / 100;
        $xml .= add_XML_value('metricpass', $metricpass);
        // Below this number of the coverage is red
        $metricerror = 0.7 * ($threshold / 100);
        $xml .= add_XML_value('metricerror', $metricerror);

        // Only execute the label-related queries if labels are being
        // displayed:
        //
        if ($project->DisplayLabels) {
            // Get the set of labels involved:
            //
            $labels = array();

            $covlabels = $db->executePrepared('
                     SELECT DISTINCT id, text
                     FROM label, label2coveragefile
                     WHERE
                         label.id=label2coveragefile.labelid
                         AND label2coveragefile.buildid=?
                 ', [intval($buildid)]);
            foreach ($covlabels as $row) {
                $labels[$row['id']] = $row['text'];
            }

            // For each label, compute the percentcoverage for files with
            // that label:
            //
            if (count($labels) > 0) {
                $xml .= '<labels>';

                foreach ($labels as $id => $label) {
                    $row = $db->executePreparedSingleRow('
                       SELECT
                           SUM(loctested) AS loctested,
                           SUM(locuntested) AS locuntested
                       FROM label2coveragefile, coverage
                       WHERE
                           label2coveragefile.labelid=?
                           AND label2coveragefile.buildid=?
                           AND coverage.buildid=label2coveragefile.buildid
                           AND coverage.fileid=label2coveragefile.coveragefileid
                   ', [intval($id), intval($buildid)]);

                    $loctested = intval($row['loctested']);
                    $locuntested = intval($row['locuntested']);
                    $percentcoverage = compute_percentcoverage($loctested, $locuntested);

                    $xml .= '<label>';
                    $xml .= add_XML_value('name', $label);
                    $xml .= add_XML_value('percentcoverage', $percentcoverage);
                    $xml .= '</label>';
                }

                $xml .= '</labels>';
            }
        }

        $coveredfiles = $db->executePreparedSingleRow("
                    SELECT count(covered) AS c
                    FROM coverage
                    WHERE buildid=? AND covered='1'
                ", [intval($buildid)]);
        $ncoveredfiles = intval($coveredfiles['c']);

        $files = $db->executePreparedSingleRow('
             SELECT count(covered) AS c
             FROM coverage
             WHERE buildid=?
         ', [intval($buildid)]);
        $nfiles = intval($files['c']);

        $xml .= add_XML_value('totalcovered', $ncoveredfiles);
        $xml .= add_XML_value('totalfiles', $nfiles);
        $xml .= add_XML_value('buildid', $buildid);
        $xml .= add_XML_value('userid', $userid);

        $xml .= add_XML_value('showcoveragecode', $projectshowcoveragecode);
        $xml .= add_XML_value('displaylabels', $project->DisplayLabels);

        $nsatisfactorycoveredfiles = 0;
        $coveragetype = 'gcov'; // default coverage to avoid warning

        // Coverage files
        $coveragefile = $db->executePrepared('
                    SELECT
                        c.locuntested,
                        c.loctested,
                        c.branchstested,
                        c.branchsuntested,
                        c.functionstested,
                        c.functionsuntested,
                        cf.fullpath
                    FROM
                        coverage AS c,
                        coveragefile AS cf
                    WHERE
                        c.buildid=?
                        AND c.covered=1
                        AND c.fileid=cf.id
                ', [intval($buildid)]);

        $directories = array();
        $covfile_array = array();
        foreach ($coveragefile as $coveragefile_array) {
            $covfile['covered'] = 1;

            // Compute the coverage metric for bullseye.  (branch coverage without line coverage)
            if (
                ($coveragefile_array['loctested'] == 0 && $coveragefile_array['locuntested'] == 0) &&
                ($coveragefile_array['branchstested'] > 0 || $coveragefile_array['branchsuntested'] > 0 ||
                    $coveragefile_array['functionstested'] > 0 || $coveragefile_array['functionsuntested'] > 0)) {
                // Metric coverage
                $metric = 0;
                if ($coveragefile_array['functionstested'] + $coveragefile_array['functionsuntested'] > 0) {
                    $metric += $coveragefile_array['functionstested'] / ($coveragefile_array['functionstested'] + $coveragefile_array['functionsuntested']);
                }
                if ($coveragefile_array['branchstested'] + $coveragefile_array['branchsuntested'] > 0) {
                    $metric += $coveragefile_array['branchstested'] / ($coveragefile_array['branchstested'] + $coveragefile_array['branchsuntested']);
                    $metric /= 2.0;
                }

                $covfile['percentcoverage'] = sprintf('%3.2f', $metric * 100);
                $covfile['coveragemetric'] = $metric;
                $coveragetype = 'bullseye';
            } else {
                // coverage metric for gcov

                $covfile['coveragemetric'] = ($coveragefile_array['loctested'] + 10) / ($coveragefile_array['loctested'] + $coveragefile_array['locuntested'] + 10);
                $coveragetype = 'gcov';
                $covfile['percentcoverage'] = sprintf('%3.2f', $coveragefile_array['loctested'] / ($coveragefile_array['loctested'] + $coveragefile_array['locuntested']) * 100);
            }

            // Add the number of satisfactory covered files
            if ($covfile['coveragemetric'] >= $metricpass) {
                $nsatisfactorycoveredfiles++;
            }

            // Store the directories path only for non-complete (100% coverage) files
            if ($covfile['coveragemetric'] != 1.0) {
                $fullpath = $coveragefile_array['fullpath'];
                if (str_starts_with($fullpath, './')) {
                    $fullpath = substr($fullpath, 2);
                }
                $fullpath = dirname($fullpath);
                $directories[$fullpath] = 1;
            }
            $covfile_array[] = $covfile;
        }

        // Add the coverage type
        $xml .= add_XML_value('coveragetype', $coveragetype);
        if (isset($_GET['status'])) {
            $xml .= add_XML_value('status', $_GET['status']);
        } else {
            $xml .= add_XML_value('status', -1);
        }
        if (isset($_GET['dir'])) {
            $xml .= add_XML_value('dir', $_GET['dir']);
        }

        $xml .= add_XML_value('totalsatisfactorilycovered', $nsatisfactorycoveredfiles);
        $xml .= add_XML_value('totalunsatisfactorilycovered', $nfiles - $nsatisfactorycoveredfiles);

        $xml .= '</coverage>';

        // Add the untested files
        $coveragefile = $db->executePrepared('
                    SELECT c.buildid
                    FROM coverage AS c
                    WHERE
                        c.buildid=?
                        AND c.covered=0
                ', [intval($buildid)]);
        foreach ($coveragefile as $coveragefile_array) {
            $covfile['covered'] = 0;
            $covfile['coveragemetric'] = 0;
            $covfile_array[] = $covfile;
        }

        $ncoveragefiles = array();
        $ncoveragefiles[0] = count($directories);
        $ncoveragefiles[1] = 0;
        $ncoveragefiles[2] = 0;
        $ncoveragefiles[3] = 0;
        $ncoveragefiles[4] = 0;
        $ncoveragefiles[5] = 0;
        $ncoveragefiles[6] = 0;
        $ncoveragefiles[7] = 0;

        foreach ($covfile_array as $covfile) {
            if ($covfile['covered'] == 0) {
                $ncoveragefiles[1]++; // no coverage
            } elseif ($covfile['covered'] == 1 && $covfile['percentcoverage'] == 0) {
                $ncoveragefiles[2]++; // zero
            } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] < $metricerror) {
                $ncoveragefiles[3]++; // low
            } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] == 1.0) {
                $ncoveragefiles[6]++; // complete
            } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] >= $metricpass) {
                $ncoveragefiles[5]++; // satisfactory
            } else {
                $ncoveragefiles[4]++; // medium
            }
            $ncoveragefiles[7]++; // all
        }

        // Show the number of files covered by status
        $xml .= '<coveragefilestatus>';
        $xml .= add_XML_value('directories', $ncoveragefiles[0]);
        $xml .= add_XML_value('no', $ncoveragefiles[1]);
        $xml .= add_XML_value('zero', $ncoveragefiles[2]);
        $xml .= add_XML_value('low', $ncoveragefiles[3]);
        $xml .= add_XML_value('medium', $ncoveragefiles[4]);
        $xml .= add_XML_value('satisfactory', $ncoveragefiles[5]);
        $xml .= add_XML_value('complete', $ncoveragefiles[6]);
        $xml .= add_XML_value('all', $ncoveragefiles[7]);
        $xml .= '</coveragefilestatus>';

        // Filters:
        //
        // On this page, we don't need the 'sql' or its friend 'limit' from
        // the filterdata, since the actual sql query is deferred until
        // ajax/getviewcoverage.php (called by cdashViewCoverage.js).
        //
        $filterdata = get_filterdata_from_request();
        $xml .= $filterdata['xml'];
        $xml .= '</cdash>';

        // We first have to change to this directory so XSL knows how to find sub-templates to include.
        chdir(base_path() . '/app/cdash/public');
        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, 'viewCoverage', true),
            'project' => $project,
            'title' => 'Coverage'
        ]);
    }

    public function viewCoverageFile(): View
    {
        @$buildid = $_GET['buildid'];
        if ($buildid != null) {
            $buildid = pdo_real_escape_numeric($buildid);
        }
        @$fileid = $_GET['fileid'];
        if ($fileid != null) {
            $fileid = pdo_real_escape_numeric($fileid);
        }
        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars(pdo_real_escape_string($date));
        }

        // Checks
        if (!isset($buildid) || !is_numeric($buildid)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "Not a valid buildid!"
            ]);
        }

        @$userid = Auth::id();
        if (!isset($userid)) {
            $userid = 0;
        }

        $db = Database::getInstance();

        $build_array = $db->executePreparedSingleRow('
                   SELECT starttime, projectid FROM build WHERE id=?
               ', [intval($buildid)]);
        $projectid = intval($build_array['projectid']);
        if ($projectid === 0) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "This build doesn't exist. Maybe it has been deleted."
            ]);
        }

        checkUserPolicy($projectid);

        $project_array = $db->executePreparedSingleRow('SELECT * FROM project WHERE id=?', [$projectid]);
        if (empty($project_array)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "This project doesn't exist."
            ]);
        }

        $projectname = $project_array['name'];

        $project = new Project();
        $project->Id = $projectid;
        $project->Fill();

        $role = 0;
        $user2project = $db->executePreparedSingleRow('
                    SELECT role
                    FROM user2project
                    WHERE
                        userid=?
                        AND projectid=?
                ', [intval($userid), $projectid]);
        if (!empty($user2project)) {
            $role = $user2project['role'];
        }
        if (!$project_array['showcoveragecode'] && $role < 2) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "This project doesn't allow display of coverage code. Contact the administrator of the project."
            ]);
        }

        $xml = begin_XML_for_XSLT();
        $xml .= get_cdash_dashboard_xml_by_name($projectname, $date);

        // Build
        $xml .= '<build>';
        $build_array = $db->executePreparedSingleRow('SELECT * FROM build WHERE id=?', [intval($buildid)]);
        $siteid = $build_array['siteid'];
        $site_array = $db->executePreparedSingleRow('SELECT name FROM site WHERE id=?', [intval($siteid)]);
        $xml .= add_XML_value('site', $site_array['name']);
        $xml .= add_XML_value('buildname', $build_array['name']);
        $xml .= add_XML_value('buildid', $build_array['id']);
        $xml .= add_XML_value('buildtime', $build_array['starttime']);
        $xml .= '</build>';

        // Load coverage file.
        $coverageFile = new CoverageFile();
        $coverageFile->Id = $fileid;
        $coverageFile->Load();

        $xml .= '<coverage>';
        $xml .= add_XML_value('fullpath', $coverageFile->FullPath);

        // Generating the html file
        $file_array = explode('<br>', $coverageFile->File);
        $i = 0;

        // Load the coverage info.
        $log = new CoverageFileLog();
        $log->BuildId = $buildid;
        $log->FileId = $fileid;
        $log->Load();

        // Detect if we have branch coverage or not.
        $hasBranchCoverage = false;
        if (!empty($log->Branches)) {
            $hasBranchCoverage = true;
        }

        foreach ($file_array as $line) {
            $linenumber = $i + 1;
            $line = htmlentities($line);

            $file_array[$i] = '<span class="warning">' . str_pad(strval($linenumber), 5, ' ', STR_PAD_LEFT) . '</span>';

            if ($hasBranchCoverage) {
                if (array_key_exists("$i", $log->Branches)) {
                    $code = $log->Branches["$i"];

                    // Branch coverage data is stored as <# covered> / <total branches>.
                    $branchCoverageData = explode('/', $code);
                    if ($branchCoverageData[0] != $branchCoverageData[1]) {
                        $file_array[$i] .= '<span class="error">';
                    } else {
                        $file_array[$i] .= '<span class="normal">';
                    }
                    $file_array[$i] .= str_pad($code, 5, ' ', STR_PAD_LEFT) . '</span>';
                } else {
                    $file_array[$i] .= str_pad('', 5, ' ', STR_PAD_LEFT);
                }
            }

            if (array_key_exists($i, $log->Lines)) {
                $code = $log->Lines[$i];
                if ($code == 0) {
                    $file_array[$i] .= '<span class="error">';
                } else {
                    $file_array[$i] .= '<span class="normal">';
                }
                $file_array[$i] .= str_pad($code, 5, ' ', STR_PAD_LEFT) . ' | ' . $line;
                $file_array[$i] .= '</span>';
            } else {
                $file_array[$i] .= str_pad('', 5, ' ', STR_PAD_LEFT) . ' | ' . $line;
            }
            $i++;
        }

        $file = implode('<br>', $file_array);

        $xml .= '<file>' . utf8_encode(htmlspecialchars($file)) . '</file>';
        $xml .= '</coverage>';
        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/viewCoverageFile', true),
            'project' => $project,
            'title' => 'Coverage for ' . $coverageFile->FullPath,
        ]);
    }
}
