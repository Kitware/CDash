<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\PageTimer;
use App\Utils\TestingDay;
use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFile2User;
use CDash\Model\CoverageFileLog;
use CDash\Model\CoverageSummary;
use App\Models\Project as EloquentProject;
use CDash\Model\Project;
use CDash\Model\UserProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

require_once 'include/filterdataFunctions.php';

final class CoverageController extends AbstractBuildController
{
    public function compareCoverage(): Response|RedirectResponse
    {
        // If the project name is not set we display the table of projects.
        if (!isset($_GET['project'])) {
            return redirect('projects');
        }

        return response()->angular_view('compareCoverage');
    }

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
                'xsl_content' => 'Not a valid userid!',
            ]);
        }

        $xml = begin_XML_for_XSLT();
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
        // TODO: (williamjallen) Should this be one project in all of CDash, or one project we can see?
        if (!isset($projectid) && EloquentProject::count() === 1) {
            $projectid = EloquentProject::all()->firstOrFail()->id;
        }
        $projectid = intval($projectid);

        /** @var User $User */
        $User = Auth::user();
        $Project->Id = $projectid;
        if (!Gate::allows('edit-project', $Project)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "You don't have the permissions to access this page",
            ]);
        }

        $sql = 'SELECT id,name FROM project';
        $params = [];
        if ($User->admin) {
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

        // Change the priority of selected files, assuming that the bulk replacement takes priority.
        if (isset($_POST['changePrioritySelected'])) {
            foreach ($_POST['selectionFiles'] ?? [] as $key => $value) {
                $CoverageFile2User->FullPath = htmlspecialchars($value);
                $CoverageFile2User->SetPriority(intval($_POST['prioritySelectedSelection']));
            }
        } elseif (isset($_POST['prioritySelection'])) {
            $CoverageFile2User = new CoverageFile2User();
            $CoverageFile2User->ProjectId = $projectid;
            $CoverageFile2User->FullPath = htmlspecialchars($_POST['fullpath'] ?? '');
            $CoverageFile2User->SetPriority(intval($_POST['prioritySelection'] ?? -1));
        }

        // Remove the selected authors
        if (isset($_POST['removeAuthorsSelected'])) {
            foreach ($_POST['selectionFiles'] ?? [] as $key => $value) {
                $CoverageFile2User->FullPath = htmlspecialchars($value);
                $CoverageFile2User->RemoveAuthors();
            }
        }

        // Add the selected authors
        if (isset($_POST['addAuthorsSelected'])) {
            foreach ($_POST['selectionFiles'] ?? [] as $key => $value) {
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
            $CoverageFile2User->AssignAuthors($buildid, onlylast: true);
        }

        // Assign all authors
        if (isset($_POST['assignAllAuthors'])) {
            $CoverageFile2User->AssignAuthors($buildid);
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
                    $authors = [];

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
            $userids = DB::table('coveragefilepriority')
                        ->join('coveragefile2user', 'coveragefilepriority.id', '=', 'coveragefile2user.fileid')
                        ->where('coveragefilepriority.projectid', '=', intval($projectid))->distinct()
                        ->pluck('userid')->toArray();
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
                        $xml .= add_XML_value('name', $user !== null ? $user->full_name : '');
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
                $xml .= add_XML_value('name', $User !== null ? $User->full_name : 1);
                $xml .= '</user>';
            }

            $xml .= '</project>';
        }
        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/manageCoverage', true),
            'project' => $Project,
            'title' => 'Manage Coverage',
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

        $this->setBuildById(intval($_GET['buildid'] ?? -1));

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars($date);
        }

        if (isset($_GET['value1']) && strlen($_GET['value1']) > 0) {
            $filtercount = $_GET['filtercount'];
        } else {
            $filtercount = 0;
        }

        @$userid = Auth::id();
        if (!isset($userid)) {
            $userid = 0;
        }

        $db = Database::getInstance();

        $role = 0;
        $user2project = $db->executePreparedSingleRow('
                            SELECT role
                            FROM user2project
                            WHERE userid=? AND projectid=?
                        ', [intval($userid), intval($this->project->Id)]);
        if (!empty($user2project)) {
            $role = $user2project['role'];
        }

        $projectshowcoveragecode = 1;
        if (!$this->project->ShowCoverageCode && $role < 2) {
            $projectshowcoveragecode = 0;
        }

        $xml = begin_XML_for_XSLT();
        $xml .= get_cdash_dashboard_xml_by_name($this->project->Name, $date);
        $xml .= '<buildid>' . $this->build->Id . '</buildid>';

        $threshold = $this->project->CoverageThreshold;
        if ($this->build->GroupId > 0) {
            $row = $db->executePreparedSingleRow('
                       SELECT coveragethreshold
                       FROM subprojectgroup
                       WHERE projectid=? AND id=?
                   ', [intval($this->project->Id), intval($this->build->GroupId)]);
            if (!empty($row) && isset($row['coveragethreshold'])) {
                $threshold = intval($row['coveragethreshold']);
            }
        }

        $date = TestingDay::get($this->project, $this->build->StartTime);
        $xml .= '<menu>';
        $xml .= add_XML_value('back', 'index.php?project=' . urlencode($this->project->Name) . "&date=$date");

        $build = new Build();
        $build->Id = $this->build->Id;
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
                                  sum(branchestested) as branchestested,
                                  sum(branchesuntested) as branchesuntested
                              FROM coverage
                              WHERE buildid=?
                              GROUP BY buildid
                          ', [intval($this->build->Id)]);

        $xml .= add_XML_value('starttime', date('l, F d Y', strtotime($this->build->StartTime)));
        $xml .= add_XML_value('loctested', $coverage_array['loctested']);
        $xml .= add_XML_value('locuntested', $coverage_array['locuntested']);

        $xml .= add_XML_value('branchestested', $coverage_array['branchestested']);
        $xml .= add_XML_value('branchesuntested', $coverage_array['branchesuntested']);
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
        if ($this->project->DisplayLabels) {
            // Get the set of labels involved:
            //
            $labels = [];

            $covlabels = $db->executePrepared('
                             SELECT DISTINCT id, text
                             FROM label, label2coveragefile
                             WHERE
                                 label.id=label2coveragefile.labelid
                                 AND label2coveragefile.buildid=?
                         ', [intval($this->build->Id)]);
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
                           ', [intval($id), intval($this->build->Id)]);

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
                        ", [intval($this->build->Id)]);
        $ncoveredfiles = intval($coveredfiles['c']);

        $files = $db->executePreparedSingleRow('
                     SELECT count(covered) AS c
                     FROM coverage
                     WHERE buildid=?
                 ', [intval($this->build->Id)]);
        $nfiles = intval($files['c']);

        $xml .= add_XML_value('totalcovered', $ncoveredfiles);
        $xml .= add_XML_value('totalfiles', $nfiles);
        $xml .= add_XML_value('buildid', $this->build->Id);
        $xml .= add_XML_value('userid', $userid);

        $xml .= add_XML_value('showcoveragecode', $projectshowcoveragecode);
        $xml .= add_XML_value('displaylabels', $this->project->DisplayLabels);

        $nsatisfactorycoveredfiles = 0;
        $coveragetype = 'gcov'; // default coverage to avoid warning

        // Coverage files
        $coveragefile = $db->executePrepared('
                            SELECT
                                c.locuntested,
                                c.loctested,
                                c.branchestested,
                                c.branchesuntested,
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
                        ', [intval($this->build->Id)]);

        $directories = [];
        $covfile_array = [];
        foreach ($coveragefile as $coveragefile_array) {
            $covfile['covered'] = 1;

            // Compute the coverage metric for bullseye.  (branch coverage without line coverage)
            if (
                ($coveragefile_array['loctested'] == 0 && $coveragefile_array['locuntested'] == 0) &&
                ($coveragefile_array['branchestested'] > 0 || $coveragefile_array['branchesuntested'] > 0 ||
                    $coveragefile_array['functionstested'] > 0 || $coveragefile_array['functionsuntested'] > 0)) {
                // Metric coverage
                $metric = 0;
                if ($coveragefile_array['functionstested'] + $coveragefile_array['functionsuntested'] > 0) {
                    $metric += $coveragefile_array['functionstested'] / ($coveragefile_array['functionstested'] + $coveragefile_array['functionsuntested']);
                }
                if ($coveragefile_array['branchestested'] + $coveragefile_array['branchesuntested'] > 0) {
                    $metric += $coveragefile_array['branchestested'] / ($coveragefile_array['branchestested'] + $coveragefile_array['branchesuntested']);
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
                ', [intval($this->build->Id)]);
        foreach ($coveragefile as $coveragefile_array) {
            // TODO: (williamjallen) This loop doesn't really make sense...
            $covfile['covered'] = 0;
            $covfile['coveragemetric'] = 0;
            $covfile_array[] = $covfile;
        }

        $ncoveragefiles = [];
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
            'project' => $this->project,
            'title' => 'Coverage',
        ]);
    }

    public function viewCoverageFile(): View
    {
        $this->setBuildById(intval($_GET['buildid'] ?? 0));

        $fileid = intval($_GET['fileid'] ?? 0);


        $db = Database::getInstance();

        $role = 0;
        $user2project = $db->executePreparedSingleRow('
            SELECT role
            FROM user2project
            WHERE
                userid=?
                AND projectid=?
        ', [intval(Auth::id() ?? 0), $this->project->Id]);
        if (!empty($user2project)) {
            $role = $user2project['role'];
        }
        if (!$this->project->ShowCoverageCode && $role < 2) {
            abort(403, "This project doesn't allow display of coverage code. Contact the administrator of the project.");
        }

        // Load coverage file.
        $coverageFile = new CoverageFile();
        $coverageFile->Id = $fileid;
        $coverageFile->Load();

        // Generating the html file
        $file_array = explode('<br>', $coverageFile->File);
        $i = 0;

        // Load the coverage info.
        $log = new CoverageFileLog();
        $log->BuildId = $this->build->Id;
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

        return $this->view('coverage.coverage-file')
            ->with('coverage_file', $coverageFile)
            ->with('log', $file);
    }

    public function ajaxGetViewCoverage(): JsonResponse
    {
        @set_time_limit(0);

        $this->setBuildById(intval($_GET['buildid'] ?? -1));

        $userid = intval($_GET['userid'] ?? 0);

        $db = Database::getInstance();

        $role = 0;
        $user2project = $db->executePreparedSingleRow('
                            SELECT role
                            FROM user2project
                            WHERE userid=? AND projectid=?
                        ', [Auth::id() ?? 0, $this->project->Id]);
        if (!empty($user2project)) {
            $role = $user2project['role'];
        }

        $start = 0;
        $end = 10000000;

        /* Paging */
        if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
            $start = (int)($_GET['iDisplayStart'] ?? 0);
            $end = $start + (int)($_GET['iDisplayLength'] ?? 0);
        }

        //add columns for branches only if(total_branchesuntested+total_branchestested)>0
        $total_branchesuntested = 0;
        $total_branchestested = 0;
        $coverage_branches = $db->executePreparedSingleRow('
                                 SELECT
                                     sum(branchestested) AS total_branchestested,
                                     sum(branchesuntested) AS total_branchesuntested
                                 FROM coverage
                                 WHERE buildid = ?
                                 GROUP BY buildid
                             ', [$this->build->Id]);
        if (!empty($coverage_branches)) {
            $total_branchesuntested = intval($coverage_branches['total_branchesuntested']);
            $total_branchestested = intval($coverage_branches['total_branchestested']);
        }

        /* Sorting */
        $sortby = 'filename';
        if (isset($_GET['iSortCol_0'])) {
            if (($total_branchesuntested + $total_branchestested) > 0) {
                switch (intval($_GET['iSortCol_0'])) {
                    case 0:
                        $sortby = 'filename';
                        break;
                    case 1:
                        $sortby = 'status';
                        break;
                    case 2:
                        $sortby = 'percentage';
                        break;
                    case 3:
                        $sortby = 'lines';
                        break;
                    case 4:
                        $sortby = 'branchpercentage';
                        break;
                    case 5:
                        $sortby = 'branches';
                        break;
                    case 6:
                        $sortby = 'priority';
                        break;
                }
            } else {
                switch (intval($_GET['iSortCol_0'])) {
                    case 0:
                        $sortby = 'filename';
                        break;
                    case 1:
                        $sortby = 'status';
                        break;
                    case 2:
                        $sortby = 'percentage';
                        break;
                    case 3:
                        $sortby = 'lines';
                        break;
                    case 5:
                        $sortby = 'priority';
                        break;
                }
            }
        }

        $SQLsearchTerm = '';
        $SQLsearchTermParams = [];
        if (isset($_GET['sSearch']) && $_GET['sSearch'] != '') {
            $SQLsearchTerm = " AND cf.fullpath LIKE ?";
            $SQLsearchTermParams[] = '%' . htmlspecialchars($_GET['sSearch']) . '%';
        }

        $SQLDisplayAuthors = '';
        $SQLDisplayAuthor = '';
        if ($userid > 0) {
            $SQLDisplayAuthor = ', cfu.userid ';
            $SQLDisplayAuthors = ' LEFT JOIN coveragefile2user AS cfu ON (cfu.fileid=cf.id) ';
        }

        // Filters:
        //
        $filterdata = get_filterdata_from_request();
        $filter_sql = $filterdata['sql'];
        $limit_sql = '';
        $limit_sql_params = [];
        if ($filterdata['limit'] > 0) {
            $limit_sql = ' LIMIT ?';
            $limit_sql_params = [(int) $filterdata['limit']];
        }

        if (isset($_GET['dir']) && $_GET['dir'] != '') {
            $escaped_dir = htmlspecialchars($_GET['dir']);
            $SQLsearchTerm .= " AND (cf.fullpath LIKE CONCAT(?, '%') OR cf.fullpath LIKE CONCAT('./', ?, '%'))";
            $SQLsearchTermParams[] = $escaped_dir;
            $SQLsearchTermParams[] = $escaped_dir;
        }

        // Coverage files
        $coveragefile = $db->executePrepared("
                            SELECT
                                cf.fullpath,
                                c.fileid,
                                c.locuntested,
                                c.loctested,
                                c.branchestested,
                                c.branchesuntested,
                                c.functionstested,
                                c.functionsuntested,
                                cfp.priority
                                $SQLDisplayAuthor
                            FROM
                                coverage AS c,
                                coveragefile AS cf
                            $SQLDisplayAuthors
                            LEFT JOIN coveragefilepriority AS cfp ON (
                                cfp.fullpath=cf.fullpath
                                AND projectid=?
                            )
                            WHERE
                                c.buildid=?
                                AND cf.id=c.fileid
                                AND c.covered=1
                                $filter_sql
                                $SQLsearchTerm
                            $limit_sql
                        ", array_merge([$this->project->Id, $this->build->Id], $SQLsearchTermParams, $limit_sql_params));

        // Add the coverage type
        $status = (int)($_GET['status'] ?? -1);

        $covfile_array = [];
        foreach ($coveragefile as $coveragefile_array) {
            $covfile = [];
            $covfile['filename'] = substr($coveragefile_array['fullpath'], strrpos($coveragefile_array['fullpath'], '/') + 1);
            $fullpath = $coveragefile_array['fullpath'];
            // Remove the ./ so that it's cleaner
            if (str_starts_with($fullpath, './')) {
                $fullpath = substr($fullpath, 2);
            }
            if (isset($_GET['dir']) && $_GET['dir'] != '' && $_GET['dir'] !== '.') {
                $fullpath = substr($fullpath, strlen($_GET['dir']) + 1);
            }

            $covfile['fullpath'] = $fullpath;
            $covfile['fileid'] = $coveragefile_array['fileid'];
            $covfile['locuntested'] = $coveragefile_array['locuntested'];
            $covfile['loctested'] = $coveragefile_array['loctested'];
            $covfile['covered'] = 1;
            // Compute the coverage metric for bullseye (branch coverage without line coverage)
            if (($coveragefile_array['loctested'] == 0 &&
                    $coveragefile_array['locuntested'] == 0) &&
                ($coveragefile_array['branchestested'] > 0 ||
                    $coveragefile_array['branchesuntested'] > 0 ||
                    $coveragefile_array['functionstested'] > 0 ||
                    $coveragefile_array['functionsuntested'] > 0)) {
                // Metric coverage
                $metric = 0;
                if ($coveragefile_array['functionstested'] + $coveragefile_array['functionsuntested'] > 0) {
                    $metric += $coveragefile_array['functionstested'] / ($coveragefile_array['functionstested'] + $coveragefile_array['functionsuntested']);
                }
                if ($coveragefile_array['branchestested'] + $coveragefile_array['branchesuntested'] > 0) {
                    $metric += $coveragefile_array['branchestested'] / ($coveragefile_array['branchestested'] + $coveragefile_array['branchesuntested']);
                    $metric /= 2.0;
                }
                $covfile['branchesuntested'] = $coveragefile_array['branchesuntested'];
                $covfile['branchestested'] = $coveragefile_array['branchestested'];
                $covfile['functionsuntested'] = $coveragefile_array['functionsuntested'];
                $covfile['functionstested'] = $coveragefile_array['functionstested'];

                $covfile['percentcoverage'] = sprintf('%3.2f', $metric * 100);
                $covfile['coveragemetric'] = $metric;
                $coveragetype = 'bullseye';
            } else {
                // coverage metric for gcov
                $metric = 0;
                $covfile['branchesuntested'] = $coveragefile_array['branchesuntested'];
                $covfile['branchestested'] = $coveragefile_array['branchestested'];
                if (($covfile['branchestested'] + $covfile['branchesuntested']) > 0) {
                    $metric += $covfile['branchestested'] / ($covfile['branchestested'] + $covfile['branchesuntested']);
                }
                $covfile['branchpercentcoverage'] = sprintf('%3.2f', $metric * 100);
                $covfile['branchcoveragemetric'] = $metric;

                $covfile['percentcoverage'] = sprintf('%3.2f', $covfile['loctested'] / ($covfile['loctested'] + $covfile['locuntested']) * 100);
                $covfile['coveragemetric'] = ($covfile['loctested'] + 10) / ($covfile['loctested'] + $covfile['locuntested'] + 10);
                $coveragetype = 'gcov';
            }

            // Add the priority
            $covfile['priority'] = $coveragefile_array['priority'];

            // If the user is logged in we set the users
            if (isset($coveragefile_array['userid'])) {
                $covfile['user'] = $coveragefile_array['userid'];
            }
            if ($covfile['coveragemetric'] != 1.0 || $status !== -1) {
                $covfile_array[] = $covfile;
            }
        }


        // Contruct the directory view
        if ($status === -1) {
            $directory_array = [];
            foreach ($covfile_array as $covfile) {
                $fullpath = $covfile['fullpath'];
                $fullpath = dirname($fullpath);
                if (!isset($directory_array[$fullpath])) {
                    $directory_array[$fullpath] = [];
                    $directory_array[$fullpath]['priority'] = 0;
                    $directory_array[$fullpath]['directory'] = 1;
                    $directory_array[$fullpath]['covered'] = 1;
                    $directory_array[$fullpath]['fileid'] = 0;
                    $directory_array[$fullpath]['locuntested'] = 0;
                    $directory_array[$fullpath]['loctested'] = 0;
                    $directory_array[$fullpath]['branchesuntested'] = 0;
                    $directory_array[$fullpath]['branchestested'] = 0;
                    $directory_array[$fullpath]['functionsuntested'] = 0;
                    $directory_array[$fullpath]['functionstested'] = 0;
                    $directory_array[$fullpath]['percentcoverage'] = 0;
                    $directory_array[$fullpath]['coveragemetric'] = 0;
                    $directory_array[$fullpath]['nfiles'] = 0;
                    $directory_array[$fullpath]['branchpercentcoverage'] = 0;
                    $directory_array[$fullpath]['branchcoveragemetric'] = 0;
                }

                $directory_array[$fullpath]['fullpath'] = $fullpath;
                $directory_array[$fullpath]['locuntested'] += $covfile['locuntested'];
                $directory_array[$fullpath]['loctested'] += $covfile['loctested'];
                if (isset($covfile['branchesuntested'])) {
                    $directory_array[$fullpath]['branchesuntested'] += $covfile['branchesuntested'];
                    $directory_array[$fullpath]['branchestested'] += $covfile['branchestested'];

                    $directory_array[$fullpath]['branchcoveragemetric'] += $covfile['branchcoveragemetric'];
                }
                if (isset($covfile['functionsuntested'])) {
                    $directory_array[$fullpath]['functionsuntested'] += $covfile['functionsuntested'];
                    $directory_array[$fullpath]['functionstested'] += $covfile['functionstested'];
                }
                $directory_array[$fullpath]['coveragemetric'] += $covfile['coveragemetric'];
                $directory_array[$fullpath]['nfiles']++;
            }

            // Compute the average
            foreach ($directory_array as $fullpath => $covdir) {
                $directory_array[$fullpath]['percentcoverage'] = sprintf('%3.2f',
                    100.0 * ($covdir['loctested'] / ($covdir['loctested'] + $covdir['locuntested'])));
                $directory_array[$fullpath]['coveragemetric'] = sprintf('%3.2f', $covdir['coveragemetric'] / $covdir['nfiles']);

                // Compute the branch average
                if ($coveragetype === 'gcov') {
                    $directory_array[$fullpath]['branchpercentcoverage'] = sprintf('%3.2f', 0);
                    if (($covdir['branchestested'] + $covdir['branchesuntested']) > 0) {
                        $directory_array[$fullpath]['branchpercentcoverage'] = sprintf('%3.2f',
                            100.0 * ($covdir['branchestested'] / ($covdir['branchestested'] + $covdir['branchesuntested'])));
                    }
                    $directory_array[$fullpath]['branchcoveragemetric'] = sprintf('%3.2f', $covdir['branchcoveragemetric']);
                }
            }

            $covfile_array = array_merge($covfile_array, $directory_array);
        } elseif ($status == 0) {
            // Add the untested files if the coverage is low

            $coveragefile = $db->executePrepared("
                        SELECT
                            cf.fullpath,
                            cfp.priority
                            $SQLDisplayAuthor
                        FROM
                            coverage AS c,
                            coveragefile AS cf
                            $SQLDisplayAuthors
                        LEFT JOIN coveragefilepriority AS cfp ON (
                            cfp.fullpath=cf.fullpath
                            AND projectid=?
                        )
                        WHERE
                            c.buildid=?
                            AND cf.id=c.fileid
                            AND c.covered=0
                            $SQLsearchTerm
                    ", [$this->project->Id, $this->build->Id]);

            foreach ($coveragefile as $coveragefile_array) {
                $covfile = [];
                $covfile['filename'] = substr($coveragefile_array['fullpath'], strrpos($coveragefile_array['fullpath'], '/') + 1);
                $covfile['fullpath'] = $coveragefile_array['fullpath'];
                $covfile['fileid'] = 0;
                $covfile['covered'] = 0;
                $covfile['locuntested'] = 0;
                $covfile['loctested'] = 0;
                $covfile['branchesuntested'] = 0;
                $covfile['branchestested'] = 0;
                $covfile['functionsuntested'] = 0;
                $covfile['functionstested'] = 0;
                $covfile['percentcoverage'] = 0;
                $covfile['coveragemetric'] = 0;

                $covfile['priority'] = $coveragefile_array['priority'];
                if (isset($coveragefile_array['userid'])) {
                    $covfile['user'] = $coveragefile_array['userid'];
                }
                $covfile_array[] = $covfile;
            }
        }

        // Array to return to the datatable
        $output = [
            'sEcho' => intval($_GET['sEcho']),
            'aaData' => [],
        ];

        $sortdir = 'asc';
        if (isset($_GET['sSortDir_0'])) {
            $sortdir = $_GET['sSortDir_0'];
        }
        usort($covfile_array, [$this, "sort_{$sortby}"]);
        if ($sortdir === 'desc') {
            $covfile_array = array_reverse($covfile_array);
        }

        $ncoveragefiles = 0;

        foreach ($covfile_array as $covfile) {
            // Show only the low coverage
            if (isset($covfile['directory'])) {
                $filestatus = -1; //no
            } elseif ($covfile['covered'] == 0) {
                $filestatus = 0; //no
            } elseif ($covfile['covered'] == 1 && $covfile['percentcoverage'] == 0.0) {
                $filestatus = 1; //zero
            } elseif (($covfile['covered'] == 1 && $covfile['coveragemetric'] < $_GET['metricerror'])) {
                $filestatus = 2; //low
            } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] == 1.0) {
                $filestatus = 5; //complete
            } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] >= $_GET['metricpass']) {
                $filestatus = 4; // satisfactory
            } else {
                $filestatus = 3; // medium
            }
            if ($covfile['covered'] == 1 && $status === 6) {
                $filestatus = 6; // All
            }

            if ($status != $filestatus) {
                continue;
            }
            $ncoveragefiles++;
            if ($ncoveragefiles < $start) {
                continue;
            } elseif ($ncoveragefiles > $end) {
                break;
            }

            // For display purposes
            $roundedpercentage = round($covfile['percentcoverage']);
            if ($roundedpercentage > 98) {
                $roundedpercentage = 98;
            };

            // For display branch purposes
            if ($coveragetype == 'gcov') {
                $roundedpercentage2 = round($covfile['branchpercentcoverage']);
                if ($roundedpercentage2 > 98) {
                    $roundedpercentage2 = 98;
                };
            }

            $row = [];

            // First column (Filename)
            if ($status == -1) {
                //directory view

                $row[] = '<a href="viewCoverage.php?buildid=' . $this->build->Id . '&#38;status=6&#38;dir=' . $covfile['fullpath'] . '">' . $covfile['fullpath'] . '</a>';
            } elseif (!$covfile['covered'] || !($this->project->ShowCoverageCode || $role >= Project::PROJECT_ADMIN)) {
                $row[] = $covfile['fullpath'];
            } else {
                $row[] = '<a href="viewCoverageFile.php?buildid=' . $this->build->Id . '&#38;fileid=' . $covfile['fileid'] . '">' . $covfile['fullpath'] . '</a>';
            }

            // Second column (Status)
            switch ($status) {
                case 0:
                    $row[] = 'No';
                    break;
                case 1:
                    $row[] = 'Zero';
                    break;
                case 2:
                    $row[] = 'Low';
                    break;
                case 3:
                    $row[] = 'Medium';
                    break;
                case 4:
                    $row[] = 'Satisfactory';
                    break;
                case 5:
                    $row[] = 'Complete';
                    break;
                case 6:
                case -1:
                    if ($covfile['covered'] == 0) {
                        $row[] = 'N/A'; // No coverage
                    } elseif ($covfile['covered'] == 1 && $covfile['percentcoverage'] == 0.0) {
                        $row[] = 'Zero'; // zero
                    } elseif (($covfile['covered'] == 1 && $covfile['coveragemetric'] < $_GET['metricerror'])) {
                        $row[] = 'Low'; // low
                    } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] == 1.0) {
                        $row[] = 'Complete'; //complete
                    } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] >= $_GET['metricpass']) {
                        $row[] = 'Satisfactory'; // satisfactory
                    } else {
                        $row[] = 'Medium'; // medium
                    }
                    break;
            }

            // Third column (Percentage)
            $thirdcolumn = '<div style="position:relative; width: 190px;">
               <div style="position:relative; float:left;
               width: 123px; height: 12px; background: #bdbdbd url(\'img/progressbar.gif\') top left no-repeat;">
               <div class=';
            switch ($status) {
                case 0:
                    $thirdcolumn .= '"error" ';
                    break;
                case 1:
                    $thirdcolumn .= '"error" ';
                    break;
                case 2:
                    $thirdcolumn .= '"error" ';
                    break;
                case 3:
                    $thirdcolumn .= '"warning" ';
                    break;
                case 4:
                    $thirdcolumn .= '"normal" ';
                    break;
                case 5:
                    $thirdcolumn .= '"normal" ';
                    break;
                case 6:
                case -1:
                    if (($covfile['coveragemetric'] < $_GET['metricerror'])) {
                        $thirdcolumn .= '"error"'; //low
                    } elseif ($covfile['coveragemetric'] == 1.0) {
                        $thirdcolumn .= '"normal"'; //complete
                    } elseif ($covfile['coveragemetric'] >= $_GET['metricpass']) {
                        $thirdcolumn .= '"normal"'; // satisfactory
                    } else {
                        $thirdcolumn .= '"warning"'; // medium
                    }
                    break;
            }
            $thirdcolumn .= ' style="height: 10px;margin-left:1px; ';
            $thirdcolumn .= 'border-top:1px solid grey; border-top:1px solid grey; ';
            $thirdcolumn .= 'width:' . $roundedpercentage . '%;">';
            $thirdcolumn .= '</div></div><div class="percentvalue" style="position:relative; float:left; margin-left:10px">' . $covfile['percentcoverage'] . '%</div></div>';
            $row[] = $thirdcolumn;

            // Fourth column (Line not covered)
            $fourthcolumn = '';
            if ($coveragetype == 'gcov') {
                $fourthcolumn = '<span';
                if ($covfile['covered'] == 0) {
                    $fourthcolumn .= ' class="error">' . $covfile['locuntested'] . '</span>';
                } else {
                    // covered > 0

                    switch ($status) {
                        case 0:
                            $fourthcolumn .= ' class="error">';
                            break;
                        case 1:
                            $fourthcolumn .= ' class="error">';
                            break;
                        case 2:
                            $fourthcolumn .= ' class="error">';
                            break;
                        case 3:
                            $fourthcolumn .= ' class="warning">';
                            break;
                        case 4:
                            $fourthcolumn .= ' class="normal">';
                            break;
                        case 5:
                            $fourthcolumn .= ' class="normal">';
                            break;
                        case 6:
                        case -1:
                            if (($covfile['coveragemetric'] < $_GET['metricerror'])) {
                                $fourthcolumn .= ' class="error">'; //low
                            } elseif ($covfile['coveragemetric'] == 1.0) {
                                $fourthcolumn .= ' class="normal">'; //complete
                            } elseif ($covfile['coveragemetric'] >= $_GET['metricpass']) {
                                $fourthcolumn .= ' class="normal">'; // satisfactory
                            } else {
                                $fourthcolumn .= ' class="warning">'; // medium
                            }
                            break;
                    }
                    $totalloc = $covfile['loctested'] + $covfile['locuntested'];
                    $fourthcolumn .= $covfile['locuntested'] . '/' . $totalloc . '</span>';
                }
                $row[] = $fourthcolumn;
            } elseif ($coveragetype === 'bullseye') {
                $fourthcolumn = '<span';
                // branches
                if ($covfile['covered'] == 0) {
                    $fourthcolumn .= ' class="error">' . $covfile['branchesuntested'] . '</span>';
                } else {
                    // covered > 0

                    switch ($status) {
                        case 0:
                            $fourthcolumn .= ' class="error">';
                            break;
                        case 1:
                            $fourthcolumn .= ' class="error">';
                            break;
                        case 2:
                            $fourthcolumn .= ' class="error">';
                            break;
                        case 3:
                            $fourthcolumn .= ' class="warning">';
                            break;
                        case 4:
                            $fourthcolumn .= ' class="normal">';
                            break;
                        case 5:
                            $fourthcolumn .= ' class="normal">';
                            break;
                        case 6:
                        case -1:
                            if (($covfile['coveragemetric'] < $_GET['metricerror'])) {
                                $fourthcolumn .= ' class="error">'; //low
                            } elseif ($covfile['coveragemetric'] == 1.0) {
                                $fourthcolumn .= ' class="normal">'; //complete
                            } elseif ($covfile['coveragemetric'] >= $_GET['metricpass']) {
                                $fourthcolumn .= ' class="normal">'; // satisfactory
                            } else {
                                $fourthcolumn .= ' class="warning">'; // medium
                            }
                            break;
                    }
                    $totalloc = @$covfile['branchestested'] + @$covfile['branchesuntested'];
                    $fourthcolumn .= $covfile['branchesuntested'] . '/' . $totalloc . '</span>';
                }
                $row[] = $fourthcolumn;

                $fourthcolumn2 = '<span';
                //functions
                if ($covfile['covered'] == 0) {
                    $fourthcolumn2 .= ' class="error">0</span>';
                } else {
                    // covered > 0

                    switch ($status) {
                        case 0:
                            $fourthcolumn2 .= ' class="error">';
                            break;
                        case 1:
                            $fourthcolumn2 .= ' class="error">';
                            break;
                        case 2:
                            $fourthcolumn2 .= ' class="error">';
                            break;
                        case 3:
                            $fourthcolumn2 .= ' class="warning">';
                            break;
                        case 4:
                            $fourthcolumn2 .= ' class="normal">';
                            break;
                        case 5:
                            $fourthcolumn2 .= ' class="normal">';
                            break;
                        case 6:
                        case -1:
                            if (($covfile['coveragemetric'] < $_GET['metricerror'])) {
                                $fourthcolumn2 .= ' class="error">'; //low
                            } elseif ($covfile['coveragemetric'] == 1.0) {
                                $fourthcolumn2 .= ' class="normal">'; //complete
                            } elseif ($covfile['coveragemetric'] >= $_GET['metricpass']) {
                                $fourthcolumn2 .= ' class="normal">'; // satisfactory
                            } else {
                                $fourthcolumn2 .= ' class="warning">'; // medium
                            }
                            break;
                    }
                    $totalfunctions = @$covfile['functionstested'] + @$covfile['functionsuntested'];
                    $fourthcolumn2 .= $covfile['functionsuntested'] . '/' . $totalfunctions . '</span>';
                }
                $row[] = $fourthcolumn2;
            } else {
                // avoid displaying a DataTables warning to our user if coveragetype is
                // blank or unrecognized.
                $row[] = $fourthcolumn;
            }

            //Next column (Branch Percentage)
            if ($coveragetype === 'gcov' && ($total_branchestested + $total_branchesuntested) > 0) {
                $nextcolumn = '<div style="position:relative; width: 190px;">
                   <div style="position:relative; float:left;
                   width: 123px; height: 12px; background: #bdbdbd url(\'img/progressbar.gif\') top left no-repeat;">
                   <div class=';
                switch ($status) {
                    case 0:
                        $nextcolumn .= '"error" ';
                        break;
                    case 1:
                        $nextcolumn .= '"error" ';
                        break;
                    case 2:
                        $nextcolumn .= '"error" ';
                        break;
                    case 3:
                        $nextcolumn .= '"warning" ';
                        break;
                    case 4:
                        $nextcolumn .= '"normal" ';
                        break;
                    case 5:
                        $nextcolumn .= '"normal" ';
                        break;
                    case 6:
                    case -1:
                        if (($covfile['branchcoveragemetric'] < $_GET['metricerror'])) {
                            $nextcolumn .= '"error"'; //low
                        } elseif ($covfile['branchcoveragemetric'] == 1.0) {
                            $nextcolumn .= '"normal"'; //complete
                        } elseif ($covfile['branchcoveragemetric'] >= $_GET['metricpass']) {
                            $nextcolumn .= '"normal"'; // satisfactory
                        } else {
                            $nextcolumn .= '"warning"'; // medium
                        }
                        break;
                }
                $nextcolumn .= 'style="height: 10px;margin-left:1px; ';
                $nextcolumn .= 'border-top:1px solid grey; border-top:1px solid grey; ';
                $nextcolumn .= 'width:' . $roundedpercentage2 . '%;">';
                $nextcolumn .= '</div></div><div class="percentvalue" style="position:relative; float:left; margin-left:10px">' . $covfile['branchpercentcoverage'] . '%</div></div>';
                $row[] = $nextcolumn;

                // Next column (branch not covered)
                $nextcolumn2 = '';
                $nextcolumn2 = '<span';
                if ($covfile['covered'] == 0) {
                    $nextcolumn2 .= ' class="error">' . $covfile['branchestested'] . '</span>';
                } else {
                    // covered > 0

                    switch ($status) {
                        case 0:
                            $nextcolumn2 .= ' class="error">';
                            break;
                        case 1:
                            $nextcolumn2 .= ' class="error">';
                            break;
                        case 2:
                            $nextcolumn2 .= ' class="error">';
                            break;
                        case 3:
                            $nextcolumn2 .= ' class="warning">';
                            break;
                        case 4:
                            $nextcolumn2 .= ' class="normal">';
                            break;
                        case 5:
                            $nextcolumn2 .= ' class="normal">';
                            break;
                        case 6:
                        case -1:
                            if (($covfile['branchcoveragemetric'] < $_GET['metricerror'])) {
                                $nextcolumn2 .= ' class="error">'; //low
                            } elseif ($covfile['branchcoveragemetric'] == 1.0) {
                                $nextcolumn2 .= ' class="normal">'; //complete
                            } elseif ($covfile['branchcoveragemetric'] >= $_GET['metricpass']) {
                                $nextcolumn2 .= ' class="normal">'; // satisfactory
                            } else {
                                $nextcolumn2 .= ' class="warning">'; // medium
                            }
                            break;
                    }
                    $totalloc = @$covfile['branchestested'] + @$covfile['branchesuntested'];
                    $nextcolumn2 .= $covfile['branchesuntested'] . '/' . $totalloc . '</span>';
                }
                $row[] = $nextcolumn2;
            }

            // Fifth column (Priority)
            // Get the priority
            $priority = 'NA';
            switch ($covfile['priority']) {
                case 0:
                    $priority = '<div>None</div>';
                    break;
                case 1:
                    $priority = '<div>Low</div>';
                    break;
                case 2:
                    $priority = '<div class="warning">Medium</div>';
                    break;
                case 3:
                    $priority = '<div class="error">High</div>';
                    break;
                case 4:
                    $priority = '<div class="error">Urgent</div>';
                    break;
            }
            $row[] = $priority;

            // Sixth colum (Authors)
            if ($userid > 0) {
                $author = '';
                if (isset($covfile['user'])) {
                    /** @var User $user */
                    $user = User::where('id', $covfile['user']);
                    $author = $user->getFullNameAttribute();
                }
                $row[] = $author;
            }

            // Seventh colum (Label)
            if (isset($_GET['displaylabels']) && $_GET['displaylabels'] == 1) {
                $fileid = $covfile['fileid'];
                $labels = '';
                $coveragelabels = $db->executePrepared('
                                      SELECT text
                                      FROM
                                          label,
                                          label2coveragefile
                                      WHERE
                                          label.id=label2coveragefile.labelid
                                          AND label2coveragefile.coveragefileid=?
                                          AND label2coveragefile.buildid=?
                                      ORDER BY text ASC
                                  ', [intval($fileid), $this->build->Id]);
                foreach ($coveragelabels as $coveragelabels_array) {
                    if ($labels != '') {
                        $labels .= ', ';
                    }
                    $labels .= $coveragelabels_array['text'];
                }

                $row[] = $labels;
            }

            $output['aaData'][] = $row;
        }

        switch ($status) {
            case -1:
                $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['ndirectories'];
                break;
            case 0:
                $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nno'];
                break;
            case 1:
                $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nzero'];
                break;
            case 2:
                $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nlow'];
                break;
            case 3:
                $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nmedium'];
                break;
            case 4:
                $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nsatisfactory'];
                break;
            case 5:
                $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['ncomplete'];
                break;
            case 6:
                $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nall'];
                break;
        }

        return response()->json(cast_data_for_JSON($output));
    }

    private function sort_filename($a, $b): int
    {
        if ($a['fullpath'] == $b['fullpath']) {
            return 0;
        }
        return $a['fullpath'] > $b['fullpath'] ? 1 : -1;
    }

    private function sort_status($a, $b): int
    {
        if ($a['coveragemetric'] == $b['coveragemetric']) {
            return 0;
        }
        return $a['coveragemetric'] > $b['coveragemetric'] ? 1 : -1;
    }

    private function sort_percentage($a, $b): int
    {
        if ($a['percentcoverage'] == $b['percentcoverage']) {
            return 0;
        }
        return $a['percentcoverage'] > $b['percentcoverage'] ? 1 : -1;
    }

    private function sort_branchpercentage($a, $b): int
    {
        if ($a['branchpercentcoverage'] == $b['branchpercentcoverage']) {
            return 0;
        }
        return $a['branchpercentcoverage'] > $b['branchpercentcoverage'] ? 1 : -1;
    }

    private function sort_lines($a, $b): int
    {
        if ($a['locuntested'] == $b['locuntested']) {
            return 0;
        }
        return $a['locuntested'] > $b['locuntested'] ? 1 : -1;
    }

    private function sort_branches($a, $b): int
    {
        if ($a['branchesuntested'] == $b['branchesuntested']) {
            return 0;
        }
        return $a['branchesuntested'] > $b['branchesuntested'] ? 1 : -1;
    }

    private function sort_priority($a, $b): int
    {
        if ($a['priority'] == $b['priority']) {
            return 0;
        }
        return $a['priority'] > $b['priority'] ? 1 : -1;
    }

    public function ajaxShowCoverageGraph(): View
    {
        $buildid = $_GET['buildid'];
        if (!isset($buildid) || !is_numeric($buildid)) {
            abort(400, 'Not a valid buildid!');
        }
        $this->setBuildById((int) $buildid);

        $buildtype = $this->build->Type;
        $buildname = $this->build->Name;
        $siteid = $this->build->SiteId;
        $starttime = $this->build->StartTime;
        $projectid = $this->build->ProjectId;

        // Find the other builds
        $previousbuilds = DB::select('
                      SELECT id, starttime, endtime, loctested, locuntested
                      FROM build, coveragesummary as cs
                      WHERE
                          cs.buildid=build.id
                          AND siteid=?
                          AND type=?
                          AND name=?
                          AND projectid=?
                          AND starttime<=?
                      ORDER BY starttime ASC
                  ', [$siteid, $buildtype, $buildname, $projectid, $starttime]);

        return view('coverage.ajax-coverage-graph')
            ->with('previousbuilds', $previousbuilds);
    }

    public function apiCompareCoverage(): JsonResponse
    {
        $pageTimer = new PageTimer();

        $this->setProjectByName(htmlspecialchars($_GET['project'] ?? ''));

        $date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : null;

        [$previousdate, $currentstarttime, $nextdate] = get_dates($date, $this->project->NightlyTime);

        $response = begin_JSON_response();
        $response['title'] = $this->project->Name . ' - Compare Coverage';
        $response['showcalendar'] = 1;
        get_dashboard_JSON($this->project->Name, $date, $response);

        $page_id = 'compareCoverage.php';

        // Menu definition
        $beginning_timestamp = $currentstarttime;
        $end_timestamp = $currentstarttime + 3600 * 24;
        $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

        // Menu
        $menu = [];
        $projectname_encoded = urlencode($this->project->Name);
        if ($date == '') {
            $back = "index.php?project=$projectname_encoded";
        } else {
            $back = "index.php?project=$projectname_encoded&date=$date";
        }
        $menu['back'] = $back;
        $menu['previous'] = "$page_id?project=$projectname_encoded&date=$previousdate";

        $today = date(FMT_DATE, time());
        $menu['current'] = "$page_id?project=$projectname_encoded&date=$today";

        if (has_next_date($date, $currentstarttime)) {
            $menu['next'] = "$page_id?project=$projectname_encoded&date=$nextdate";
        } else {
            $menu['next'] = false;
        }
        $response['menu'] = $menu;

        // Filters
        $filterdata = get_filterdata_from_request();
        unset($filterdata['xml']);
        $response['filterdata'] = $filterdata;
        $filter_sql = $filterdata['sql'];
        $response['filterurl'] = get_filterurl();

        // Get the list of builds we're interested in.
        $build_data = self::apiCompareCoverage_get_build_data(null, (int) $this->project->Id, $beginning_UTCDate, $end_UTCDate);
        $response['builds'] = [];
        $aggregate_build = [];
        foreach ($build_data as $build_array) {
            $build = [
                'name' => $build_array['name'],
                'key' => 'build' . $build_array['id'],
                'id' => $build_array['id'],
            ];
            if ($build['name'] === 'Aggregate Coverage') {
                $aggregate_build = $build;
            } else {
                $response['builds'][] = $build;
            }
        } // end looping through builds
        // Add 'Aggregate' build last
        $response['builds'][] = $aggregate_build;

        $coverages = []; // For un-grouped subprojects
        $coveragegroups = [];  // For grouped subprojects

        // Are there any subproject groups?
        $subproject_groups = [];
        if ($this->project->GetNumberOfSubProjects($end_UTCDate) > 0) {
            $subproject_groups = $this->project->GetSubProjectGroups();
        }
        foreach ($subproject_groups as $group) {
            // Keep track of coverage info on a per-group basis.
            $groupId = $group->GetId();

            $coveragegroups[$groupId] = [];
            $coverageThreshold = $group->GetCoverageThreshold();
            $coveragegroups[$groupId]['thresholdgreen'] = $coverageThreshold;
            $coveragegroups[$groupId]['thresholdyellow'] = $coverageThreshold * 0.7;

            $coveragegroups[$groupId]['coverages'] = [];

            foreach ($response['builds'] as $build) {
                $coveragegroups[$groupId][$build['key']] = -1;
            }
            $coveragegroups[$groupId]['label'] = $group->GetName();
            $coveragegroups[$groupId]['position'] = $group->GetPosition();
        }
        if (count($subproject_groups) > 1) {
            // Add group for Total coverage.
            $coveragegroups[0] = [];
            $coverageThreshold = $this->project->CoverageThreshold;
            $coveragegroups[0]['thresholdgreen'] = $coverageThreshold;
            $coveragegroups[0]['thresholdyellow'] = $coverageThreshold * 0.7;
            foreach ($response['builds'] as $build) {
                $coveragegroups[0][$build['key']] = -1;
            }
            $coveragegroups[0]['label'] = 'Total';
            $coveragegroups[0]['position'] = 0;
        }

        // First, get the coverage data for the aggregate build.
        $build_data = self::apiCompareCoverage_get_build_data((int) $aggregate_build['id'], (int) $this->project->Id, $beginning_UTCDate, $end_UTCDate, $filter_sql);

        $coverage_response = self::apiCompareCoverage_get_coverage($build_data, $subproject_groups);

        // And make an entry in coverages for each possible subproject.

        // Grouped subprojects
        if (array_key_exists('coveragegroups', $coverage_response)) {
            foreach ($coverage_response['coveragegroups'] as $group) {
                $coveragegroups[$group['id']][$aggregate_build['key']] = $group['percentage'];
                $coveragegroups[$group['id']]['label'] = $group['label'];
                if ($group['id'] === 0) {
                    // 'Total' group is just a summary, does not contain coverages.
                    continue;
                }
                foreach ($group['coverages'] as $coverage) {
                    $subproject = self::apiCompareCoverage_create_subproject($coverage, $response['builds']);
                    $coveragegroups[$group['id']]['coverages'][] =
                        self::apiCompareCoverage_populate_subproject($subproject, $aggregate_build['key'], $coverage);
                }
            }
        }

        // Un-grouped subprojects
        if (array_key_exists('coverages', $coverage_response)) {
            foreach ($coverage_response['coverages'] as $coverage) {
                $subproject = self::apiCompareCoverage_create_subproject($coverage, $response['builds']);
                $coverages[] = self::apiCompareCoverage_populate_subproject($subproject, $aggregate_build['key'], $coverage);
            }
        }

        // Then loop through the other builds and fill in the subproject information
        foreach ($response['builds'] as $build_response) {
            $buildid = $build_response['id'];
            if ($buildid == null || $buildid == $aggregate_build['id']) {
                continue;
            }

            $build_data = self::apiCompareCoverage_get_build_data((int) $buildid, (int) $this->project->Id, $beginning_UTCDate, $end_UTCDate, $filter_sql);

            // Get the coverage data for each build.
            $coverage_response = self::apiCompareCoverage_get_coverage($build_data, $subproject_groups);

            // Grouped subprojects
            if (array_key_exists('coveragegroups', $coverage_response)) {
                foreach ($coverage_response['coveragegroups'] as $group) {
                    $coveragegroups[$group['id']]['build' . $buildid] = $group['percentage'];
                    $coveragegroups[$group['id']]['label'] = $group['label'];
                    if ($group['id'] === 0) {
                        // 'Total' group is just a summary, does not contain coverages.
                        continue;
                    }
                    foreach ($group['coverages'] as $coverage) {
                        // Find this subproject in the response
                        foreach ($coveragegroups[$group['id']]['coverages'] as $key => $subproject_response) {
                            if ($subproject_response['label'] == $coverage['label']) {
                                $coveragegroups[$group['id']]['coverages'][$key] =
                                    self::apiCompareCoverage_populate_subproject($coveragegroups[$group['id']]['coverages'][$key], 'build'.$buildid, $coverage);
                                break;
                            }
                        }
                    }
                }
            }

            // Un-grouped subprojects
            if (array_key_exists('coverages', $coverage_response)) {
                foreach ($coverage_response['coverages'] as $coverage) {
                    // Find this subproject in the response
                    foreach ($coverages as $key => $subproject_response) {
                        if ($subproject_response['label'] == $coverage['label']) {
                            $coverages[$key] = self::apiCompareCoverage_populate_subproject($subproject_response, 'build'.$buildid, $coverage);
                            break;
                        }
                    }
                }
            }
        } // end loop through builds

        if (!empty($subproject_groups)) {
            // At this point it is safe to remove any empty $coveragegroups from our response.
            $coveragegroups_response = array_filter($coveragegroups, function ($group) {
                return $group['label'] === 'Total' || !empty($group['coverages']);
            });

            // Report coveragegroups as a list, not an associative array.
            $coveragegroups_response = array_values($coveragegroups_response);

            $response['coveragegroups'] = $coveragegroups_response;
        } else {
            $coverageThreshold = $this->project->CoverageThreshold;
            $response['thresholdgreen'] = $coverageThreshold;
            $response['thresholdyellow'] = $coverageThreshold * 0.7;

            // Report coverages as a list, not an associative array.
            $response['coverages'] = array_values($coverages);
        }

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    /**
     * @param array<string,mixed> $coverage
     * @param array<string,mixed> $builds
     * @return array<string,mixed>
     */
    private static function apiCompareCoverage_create_subproject(array $coverage, array $builds): array
    {
        $subproject = [];
        $subproject['label'] = $coverage['label'];
        // Create a placeholder for each build
        foreach ($builds as $build) {
            $subproject[$build['key']] = -1;
        }
        return $subproject;
    }

    /**
     * @param array<string,mixed> $subproject
     * @param array<string,mixed> $coverage
     * @return array<string,mixed>
     */
    private static function apiCompareCoverage_populate_subproject(array $subproject, string $key, array $coverage): array
    {
        $subproject[$key] = $coverage['percentage'];
        $subproject[$key.'id'] = $coverage['buildid'];
        if (array_key_exists('percentagediff', $coverage)) {
            $percentagediff = $coverage['percentagediff'];
        } else {
            $percentagediff = null;
        }
        $subproject[$key.'percentagediff'] = $percentagediff;
        return $subproject;
    }

    /**
     * @param array<string,mixed> $build_array
     */
    private static function apiCompareCoverage_get_build_label(int $buildid, array $build_array): string
    {
        // Figure out how many labels to report for this build.
        if (!array_key_exists('numlabels', $build_array) || intval($build_array['numlabels']) === 0) {
            $num_labels = 0;
        } else {
            $num_labels = $build_array['numlabels'];
        }

        // Assign a label to this build based on how many labels it has.
        if ($num_labels == 0) {
            $build_label = '(none)';
        } elseif ($num_labels == 1) {
            // If exactly one label for this build, look it up here.
            $db = Database::getInstance();
            $label_result = $db->executePreparedSingleRow('
                                SELECT l.text
                                FROM label AS l
                                INNER JOIN label2build AS l2b ON (l.id=l2b.labelid)
                                INNER JOIN build AS b ON (l2b.buildid=b.id)
                                WHERE b.id=?
                            ', [intval($buildid)]);
            $build_label = $label_result['text'];
        } else {
            // More than one label, just report the number.
            $build_label = "($num_labels labels)";
        }

        return $build_label;
    }

    /**
     * @param array<string,mixed> $build_data
     * @param array<string,mixed> $subproject_groups
     * @return array<string,mixed>
     */
    private static function apiCompareCoverage_get_coverage(array $build_data, array $subproject_groups): array
    {
        $response = [];
        $response['coveragegroups'] = [];

        // Summarize coverage by subproject groups.
        // This happens when we have subprojects and we're looking at the children
        // of a specific build.
        $coverage_groups = [];
        foreach ($subproject_groups as $group) {
            // Keep track of coverage info on a per-group basis.
            $groupId = $group->GetId();

            $coverage_groups[$groupId] = [];
            $coverage_groups[$groupId]['label'] = $group->GetName();
            $coverage_groups[$groupId]['loctested'] = 0;
            $coverage_groups[$groupId]['locuntested'] = 0;
            $coverage_groups[$groupId]['coverages'] = [];
        }
        if (count($subproject_groups) > 1) {
            $coverage_groups[0] = [
                'label' => 'Total',
                'loctested' => 0,
                'locuntested' => 0,
            ];
        }

        // Generate the JSON response from the rows of builds.
        foreach ($build_data as $build_array) {
            $buildid = (int) $build_array['id'];
            $coverageIsGrouped = false;
            $coverage_response = [];
            $coverage_response['buildid'] = $build_array['id'];

            $percent = round(compute_percentcoverage($build_array['loctested'], $build_array['locuntested']), 2);

            if (!empty($build_array['subprojectgroup'])) {
                $groupId = $build_array['subprojectgroup'];
                if (array_key_exists($groupId, $coverage_groups)) {
                    $coverageIsGrouped = true;
                    $coverage_groups[$groupId]['loctested'] += (int) $build_array['loctested'];
                    $coverage_groups[$groupId]['locuntested'] += (int) $build_array['locuntested'];
                    if (count($subproject_groups) > 1) {
                        $coverage_groups[0]['loctested'] += (int) $build_array['loctested'];
                        $coverage_groups[0]['locuntested'] += (int) $build_array['locuntested'];
                    }
                }
            }

            $coverage_response['percentage'] = $percent;
            $coverage_response['locuntested'] = (int) $build_array['locuntested'];
            $coverage_response['loctested'] = (int) $build_array['loctested'];

            // Compute the diff
            if (!is_null($build_array['loctesteddiff']) || !is_null($build_array['locuntesteddiff'])) {
                $loctesteddiff = (int) $build_array['loctesteddiff'];
                $locuntesteddiff = (int) $build_array['locuntesteddiff'];
                $previouspercent =
                    round(($coverage_response['loctested'] - $loctesteddiff) /
                        ($coverage_response['loctested'] - $loctesteddiff +
                            $coverage_response['locuntested'] - $locuntesteddiff)
                        * 100, 2);
                $percentdiff = round($percent - $previouspercent, 2);
                $coverage_response['percentagediff'] = $percentdiff;
            }

            $coverage_response['label'] = self::apiCompareCoverage_get_build_label($buildid, $build_array);

            if ($coverageIsGrouped) {
                $coverage_groups[$groupId]['coverages'][] = $coverage_response;
            } else {
                $response['coverages'][] = $coverage_response;
            }
        } // end looping through builds

        // Generate coverage by group here.
        foreach ($coverage_groups as $groupid => $group) {
            $loctested = $group['loctested'];
            $locuntested = $group['locuntested'];
            if ($loctested === 0 && $locuntested === 0) {
                continue;
            }
            $percentage = round($loctested / ($loctested + $locuntested) * 100, 2);
            $group['percentage'] = $percentage;
            $group['id'] = $groupid;

            $response['coveragegroups'][] = $group;
        }

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private static function apiCompareCoverage_get_build_data(int|null $parentid, int $projectid, string $beginning_UTCDate, string $end_UTCDate, string $filter_sql=''): array
    {
        $query_params = [];
        if ($parentid !== null) {
            // If we have a parentid, then we should only show children of that build.
            // Date becomes irrelevant in this case.
            $parent_clause = 'AND b.parentid = ?';
            $date_clause = '';
            $query_params[] = $parentid;
        } else {
            // Only show builds that are not children.
            $parent_clause = 'AND (b.parentid = -1 OR b.parentid = 0)';
            $date_clause = "AND b.starttime < ? AND b.starttime >= ?";
            $query_params[] = $end_UTCDate;
            $query_params[] = $beginning_UTCDate;
        }
        $builds = Database::getInstance()->executePrepared("
                      SELECT
                          b.id,
                          b.parentid,
                          b.name,
                          sp.groupid AS subprojectgroup,
                          (SELECT count(buildid) FROM label2build WHERE buildid=b.id) AS numlabels,
                          cs.loctested,
                          cs.locuntested,
                          csd.loctested AS loctesteddiff,
                          csd.locuntested AS locuntesteddiff
                      FROM build AS b
                      INNER JOIN build2group AS b2g ON (b2g.buildid=b.id)
                      INNER JOIN buildgroup AS g ON (g.id=b2g.groupid)
                      INNER JOIN coveragesummary AS cs ON (cs.buildid = b.id)
                      LEFT JOIN coveragesummarydiff AS csd ON (csd.buildid = b.id)
                      LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
                      LEFT JOIN subproject AS sp ON (sp2b.subprojectid = sp.id)
                      WHERE
                          b.projectid=?
                          AND g.type='Daily'
                          AND b.type='Nightly'
                          $parent_clause
                          $date_clause
                          $filter_sql
                  ", array_merge([$projectid], $query_params));

        return $builds === false ? [] : $builds;
    }
}
