<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Lib\Parser\Bazel;

use CDash\Database;
use CDash\Lib\Parser\ParserInterface;
use CDash\Lib\Parser\SubmissionParser;
use CDash\Lib\Parser\SubmissionParserInterface;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildError;
use CDash\Model\BuildTest;
use CDash\Model\Project;
use CDash\Model\SubProject;
use CDash\Model\Test;

class BuildParser implements ParserInterface, SubmissionParserInterface
{
    use SubmissionParser;

    protected $buildId;
    protected $builds;
    protected $buildErrors;
    protected $commandLine;
    protected $configures;
    protected $hasSubProjects;
    protected $numTestsPassed;
    protected $numTestsFailed;
    protected $numTestsNotRun;
    protected $parentBuild;
    protected $project;
    protected $recordingTestOutput;
    protected $recordingTestSummary;
    protected $tests;
    protected $testsOutput;
    protected $testName;
    protected $workingDirectory;
    protected $parseConfigure;

    protected $pdo;

    /**
     * ParserInterface constructor.
     * @param $buildId
     */
    public function __construct($buildId)
    {
        $this->buildId = $buildId;
        $this->builds = [];
        $build = $this->getInstance(Build::class);
        $build->Id = $buildId;
        $build->FillFromId($build->Id);
        $this->builds[''] = $build;
        $this->parentBuild = null;

        $this->project = $this->getInstance(Project::class);
        $this->project->Id = $build->ProjectId;
        $this->project->Fill();
        $this->hasSubProjects = $this->project->GetNumberOfSubProjects() > 0;

        $this->commandLine = '';
        $this->workingDirectory = '';

        $this->numTestsPassed = [];
        $this->numTestsFailed = [];
        $this->numTestsNotRun = [];
        $this->numTestsPassed[''] = 0;
        $this->numTestsFailed[''] = 0;
        $this->numTestsNotRun[''] = 0;

        $this->buildErrors = ['' => []];
        $this->configures = [];
        if (!$this->hasSubProjects) {
            $this->initializeConfigure($build, '');
        }
        $this->recordingTestOutput = false;
        $this->recordingTestSummary = false;
        $this->tests = [];
        $this->testsOutput = [];
        $this->testName = '';
        $this->parseConfigure = true;

        $this->pdo = Database::getInstance();
    }

    /**
     * @param $fileName
     * @return bool
     */
    public function parse($fileName)
    {
        $handle = fopen($fileName, "r");
        if (!$handle) {
            add_log("Could not open {$fileName} for parsing",
                'BazelJSONHandler::Parse', LOG_ERR);
            return false;
        }

        while (($line = fgets($handle)) !== false) {
            $this->parseLine($line);
        }
        fclose($handle);

        foreach ($this->builds as $subproject_name => $build) {
            if ($this->hasSubProjects && $subproject_name == '') {
                // Skip this build if it isn't associated with a SubProject
                // and it should be.
                continue;
            }

            // Record any build errors that were found.
            $build->insertErrors = true;
            foreach ($this->buildErrors[$subproject_name] as $builderror) {
                $build->AddError($builderror);
            }
            $build->Save();

            // Update number of tests in the build table.
            $num_passed = $build->GetNumberOfPassedTests() +
                $this->numTestsPassed[$subproject_name];
            $num_failed = $build->GetNumberOfFailedTests() +
                $this->numTestsFailed[$subproject_name];
            $num_notrun = $build->GetNumberOfNotRunTests() +
                $this->numTestsNotRun[$subproject_name];
            $build->UpdateTestNumbers($num_passed, $num_failed, $num_notrun);
            $build->ComputeTestTiming();
        }

        // Save configure information.
        foreach ($this->configures as $subproject_name => $configure) {
            if ($this->hasSubProjects && $subproject_name == '') {
                // Skip this configure if it isn't associated with a SubProject
                // and it should be.
                continue;
            }
            $build = $this->builds[$subproject_name];
            $configure->BuildId = $build->Id;
            $configure->Command = $this->commandLine;

            // Only record this configuration if it doesn't already exist.
            // Otherwise we will potentially repeat the same errors.
            if (!$configure->ExistsByBuildId()) {
                if ($configure->Insert()) {
                    $configure->ComputeWarnings();
                    $configure->ComputeErrors();
                }

                // Record the number of warnings & errors with the build.
                $build->SetNumberOfConfigureWarnings(
                    $configure->NumberOfWarnings);
                $build->SetNumberOfConfigureErrors(
                    $configure->NumberOfErrors);
                $build->ComputeConfigureDifferences();

                // Update the tally of warnings & errors in the parent build,
                // if applicable.
                if (!empty($subproject_name)) {
                    $build->UpdateParentConfigureNumbers(
                        $configure->NumberOfWarnings, $configure->NumberOfErrors);
                }
            }
        }

        // Save testing information.
        foreach ($this->tests as $testdata) {
            $test = $testdata[0];
            $buildtest = $testdata[1];

            if (array_key_exists($test->Name, $this->testsOutput)) {
                $test->Output = $this->testsOutput[$test->Name];
            }

            $test->Command = $this->commandLine;
            $test->Insert();
            $test->InsertLabelAssociations($buildtest->BuildId);

            $buildtest->TestId = $test->Id;
            $buildtest->Insert();
        }
    }

    public function parseLine($line)
    {
        $json_array = json_decode($line, true);
        if (is_null($json_array)) {
            add_log('json_decode error: ' . json_last_error_msg(),
                'BazelJSONHandler::ParseLine', LOG_ERR);
            return false;
        }

        // Get the id of this message.
        $message_id = array_keys($json_array['id'])[0];
        if (!$message_id) {
            return false;
        }

        switch ($message_id) {
            case 'pattern':
                // We only examine this event to determine what package we're
                // currently building or testing.
                if ($this->hasSubProjects) {
                    $target_name = $json_array['id']['pattern']['pattern'][0];
                    $subproject_name = SubProject::GetSubProjectForPath(
                        $target_name, $this->project->Id);
                    if (!empty($subproject_name)) {
                        $this->initializeSubProjectBuild($subproject_name);
                    }
                }
                break;

            case 'progress':
                // Most of the data we care about lives in the 'progress' event.
                // We parse build errors, build warnings, and test output from
                // this event.
                if (array_key_exists('stdout', $json_array[$message_id])) {
                    // Test output gets placed in stdout, even if the test actually
                    // wrote to sdterr instead.
                    $test_pattern =
                        '/==================== Test output for (.*?):$/';

                    $stdout = $json_array['progress']['stdout'];
                    $lines = explode("\n", $stdout);
                    // Output lines can extend over multiple 'progress' events
                    $continue_line = true;

                    foreach ($lines as $line) {
                        if ($this->recordingTestOutput) {
                            if (preg_match('/={80}/', $line)) {
                                // A line of exacty 80 '='s means the end of output
                                // for this test.
                                $this->recordingTestOutput = false;
                                $this->testName = "";
                            } else {
                                if (!array_key_exists(
                                    $this->testName, $this->testsOutput)) {
                                    $this->testsOutput[$this->testName] = $line;
                                    $continue_line = false;
                                } elseif (!empty($line) && $continue_line) {
                                    // Continue line from previous 'progress' event
                                    $this->testsOutput[$this->testName] .= "$line";
                                    $continue_line = false;
                                } else {
                                    $this->testsOutput[$this->testName] .= "\n$line";
                                }
                            }
                        } elseif ($this->recordingTestSummary) {
                            $begin_line = explode(" ", $line)[0];
                            if ($begin_line === "Executed") {
                                // The summary of all tests begins with
                                // "Executed"
                                $this->recordingTestSummary = false;
                                $this->testName = "";
                            } elseif ($this->isTestName($begin_line)) {
                                // Check if this line starts with a test name
                                // (might be a different test than the one we're
                                // currently processing).
                                $this->testName = $begin_line;
                                if (!array_key_exists(
                                    $this->testName, $this->testsOutput)) {
                                    $this->testsOutput[$this->testName] = $line;
                                } else {
                                    $this->testsOutput[$this->testName] .= "\n\n$line";
                                }
                            } else {
                                // Add output to current test
                                $this->testsOutput[$this->testName] .= "\n\n$line";
                            }
                        } elseif (preg_match($test_pattern, $line, $matches) === 1
                            && count($matches) === 2) {
                            // For sharded tests, this string will be:
                            // '<test name> (shard <n> of <total>)'. Split
                            // off just the <test name> part.
                            $this->testName = explode(" ", $matches[1])[0];
                            $this->recordingTestOutput = true;
                        } else {
                            // Check if this line starts with a test name
                            $test_name = explode(" ", $line)[0];
                            if (array_key_exists($test_name, $this->testsOutput)) {
                                $this->recordingTestSummary = true;
                                $this->testName = $test_name;
                                if (!array_key_exists(
                                    $this->testName, $this->testsOutput)) {
                                    $this->testsOutput[$this->testName] = $line;
                                } else {
                                    $this->testsOutput[$this->testName] .= "\n\n$line";
                                }
                            }
                        }
                    }
                }

                if (array_key_exists('stderr', $json_array[$message_id])) {
                    // Parse through stderr line-by-line,
                    // searching for configure and build warnings and errors.
                    $stderr = $json_array[$message_id]['stderr'];
                    $warning_pattern = '/(.*?) warning: (.*?)$/i';
                    $error_pattern = '/(.*?)error: (.*?)$/i';

                    // The first two phases of a Bazel build, Loading and
                    // Analysis, will be treated as the 'Configure' step by
                    // CDash. The final phase, Execution, will be treated as
                    // the 'Build' step by CDash.
                    $configure_error_pattern = '/\s*ERROR: (.*?)BUILD/';
                    $analysis_warning_pattern = '/\s*WARNING: errors encountered while analyzing target \'(.*?)\': it will not be built.*?$/';
                    // Look for the report printed at the end of the analysis phase.
                    $analysis_report_pattern = '/(.*?)Found (.*?)target(.*?)/';

                    $log_line_number = 1;
                    $lines = explode("\n", $stderr);
                    $build_error = null;
                    $subproject_name = '';


                    foreach ($lines as $line) {
                        // Remove ANSI color codes.
                        $line = preg_replace('/\033\[[0-9;]*m/', '', $line);

                        $matches = [];

                        // Check if we're done with the 'Configure' step
                        if (preg_match($analysis_report_pattern, $line, $matches) === 1) {
                            $this->parseConfigure = false;
                        }

                        $record_configure_error = false;
                        if ($this->parseConfigure) {
                            if (preg_match($analysis_warning_pattern, $line, $matches) === 1
                                && count($matches) === 2) {
                                $source_file = str_replace(":", "/", $matches[1]);
                                $record_configure_error = true;
                                $type = 1;
                            } elseif (preg_match($configure_error_pattern, $line, $matches) === 1
                                && count($matches) === 2) {
                                $source_file = $matches[1] . "BUILD";
                                $record_configure_error = true;
                                $type = 0;
                            }
                            if ($record_configure_error) {
                                $subproject_name = '';
                                if ($this->hasSubProjects) {
                                    $subproject_name = SubProject::GetSubProjectForPath(
                                        $source_file, $this->project->Id);
                                    // Skip this defect if we cannot deduce what SubProject
                                    // it belongs to.
                                    if (empty($subproject_name)) {
                                        continue;
                                    }
                                    $this->initializeSubProjectBuild($subproject_name);
                                }
                                if ($type === 0) {
                                    // We don't know what the status should be,
                                    // other than non-zero. Use 1 as a reasonable
                                    // default.
                                    $this->configures[$subproject_name]->Status = 1;
                                    $this->configures[$subproject_name]->NumberOfErrors += 1;
                                } else {
                                    $this->configures[$subproject_name]->NumberOfWarnings += 1;
                                }

                                // Capture line as this configure's log.
                                $this->configures[$subproject_name]->Log .= "$line\n";
                            }
                        } else {
                            // Done with configure, parsing build errors and warnings
                            $record_error = false;
                            if (preg_match($warning_pattern, $line, $matches) === 1
                                && count($matches) === 3) {
                                // This line contains a warning.
                                $record_error = true;
                                $type = 1;
                            } elseif (preg_match($error_pattern, $line, $matches) === 1
                                && count($matches) === 3) {
                                // This line contains an error.
                                $record_error = true;
                                $type = 0;
                            }

                            if ($record_error) {
                                // Record any existing build error before creating
                                // a new one.
                                if (!is_null($build_error)) {
                                    $this->buildErrors[$subproject_name][] = $build_error;
                                    $subproject_name = '';
                                    $build_error = null;
                                }
                                $build_error = $this->getInstance(BuildError::class);
                                $build_error->Type = $type;
                                $build_error->LogLine = $log_line_number;
                                $build_error->PreContext = '';
                                $build_error->PostContext = '';
                                $build_error->RepeatCount = 0;

                                // Parse source file and line number from compiler
                                // output.
                                $context = $matches[1];
                                $source_file = '';
                                $source_line_number = '';
                                $colon_pos = strpos($context, ':');
                                if ($colon_pos !== false) {
                                    $source_file = substr($line, 0, $colon_pos);
                                    $colon_pos2 =
                                        strpos($context, ':', $colon_pos + 1);
                                    if ($colon_pos2 !== false) {
                                        $len = $colon_pos2 - $colon_pos - 1;
                                        $source_line_number =
                                            substr($line, $colon_pos + 1, $len);
                                    } else {
                                        $source_line_number =
                                            substr($line, $colon_pos + 1);
                                    }
                                }
                                // Make sure we found a valid line number
                                if (!is_numeric($source_line_number)) {
                                    $source_line_number = 0;
                                }

                                $build_error->Text = $line;
                                $build_error->SourceFile = $source_file;
                                $build_error->SourceLine = $source_line_number;

                                $subproject_name = '';
                                if ($this->hasSubProjects) {
                                    // Look up the subproject (if any) that contains
                                    // this source file.
                                    $subproject_name = SubProject::GetSubProjectForPath(
                                        $source_file, $this->project->Id);
                                    // Skip this defect if we cannot deduce what SubProject
                                    // it belongs to.
                                    if (empty($subproject_name)) {
                                        continue;
                                    }
                                    $this->initializeSubProjectBuild($subproject_name);
                                }
                            } elseif (!is_null($build_error)) {
                                // Record lines following the error/warning
                                // as post context.
                                $build_error->PostContext .= "$line\n";
                            }
                        }
                        $log_line_number++;
                    }

                    if (!is_null($build_error)) {
                        $this->buildErrors[$subproject_name][] = $build_error;
                        $build_error = null;
                    }
                }
                break;

            case 'started':
                // Parse out the command line that created this file.
                $this->commandLine = "bazel ";
                $this->commandLine .= $json_array['started']['command'];
                $this->commandLine .= $json_array['started']['optionsDescription'];

                $this->workingDirectory = $json_array['started']['workingDirectory'];
                break;

            case 'testResult':
                // Skip test results with children, the output is duplicated
                if (!array_key_exists('children', $json_array)) {
                    // By default, associate any tests with this->buildId.
                    $buildid = $this->buildId;
                    $subproject_name = '';
                    if ($this->hasSubProjects) {
                        // But if this project is broken up into subprojects,
                        // we may want to assign this test to one of the children
                        // builds instead.
                        $target_name = $json_array['id']['testResult']['label'];
                        $subproject_name = SubProject::GetSubProjectForPath(
                            $target_name, $this->project->Id);
                        // Skip this defect if we cannot deduce what SubProject
                        // it belongs to.
                        if (empty($subproject_name)) {
                            continue;
                        }
                        $child_build = $this->initializeSubProjectBuild($subproject_name);
                        if (!is_null($child_build)) {
                            $child_build->InsertErrors = false;
                            add_build($child_build);
                            $buildid = $child_build->Id;
                        }
                    }

                    $test_name = $json_array['id']['testResult']['label'];
                    $test_time = $json_array['testResult']['testAttemptDurationMillis'] / 1000.0;

                    if (array_key_exists('shard', $json_array['id']['testResult'])) {
                        // This test uses shards, so a Test with this name
                        // might already exist
                        $new_test = true;
                        foreach ($this->tests as $testdata) {
                            $test = $testdata[0];
                            $buildtest = $testdata[1];
                            if ($test->Name === $test_name) {
                                // Increment test time
                                $buildtest->Time += $test_time;
                                $new_test = false;
                                break;
                            }
                        }
                        if ($new_test) {
                            // We'll set the overall test status from 'testSummary'
                            $test_status = "";
                            $this->createNewTest($buildid, $test_status,
                                $test_time, $test_name, $subproject_name);
                        }
                    } else {
                        $test_status = strtolower($json_array['testResult']['status']);
                        $this->createNewTest($buildid, $test_status,
                            $test_time, $test_name, $subproject_name);
                    }
                }
                break;
            case 'testSummary':
                // By default, associate any tests with this->buildId.
                $subproject_name = '';
                if ($this->hasSubProjects) {
                    // But if this project is broken up into subprojects,
                    // we may want to assign this test to one of the children
                    // builds instead.
                    $target_name = $json_array['id']['testSummary']['label'];
                    $subproject_name = SubProject::GetSubProjectForPath(
                        $target_name, $this->project->Id);
                    // Skip this defect if we cannot deduce what SubProject
                    // it belongs to.
                    if (empty($subproject_name)) {
                        continue;
                    }
                }
                $test_name = $json_array['id']['testSummary']['label'];
                foreach ($this->tests as $testdata) {
                    $test = $testdata[0];
                    $buildtest = $testdata[1];
                    if ($test->Name === $test_name) {
                        $buildtest->Status =
                            strtolower($json_array['testSummary']['overallStatus']);
                        if ($buildtest->Status === 'failed') {
                            $this->numTestsFailed[$subproject_name]++;
                            $test->Details = 'Completed (Failed)';
                        } elseif ($buildtest->Status === 'timeout') {
                            $buildtest->Status = 'failed';
                            $this->numTestsFailed[$subproject_name]++;
                            $test->Details = 'Completed (Timeout)';
                            // "TIMEOUT" message is only in stderr, not stdout
                            // Make sure that it is displayed.
                            $this->testsOutput[$test->Name] = "TIMEOUT\n\n";
                        } elseif (!empty($buildtest->Status)) {
                            $this->numTestsPassed[$subproject_name]++;
                            $test->Details = 'Completed';
                        }
                        break;
                    }
                }
                break;
            default:
                break;
        }
    }

    private function initializeSubProjectBuild($subprojectName)
    {
        if (empty($subprojectName)) {
            return null;
        }
        $subproject = $this->getInstance(SubProject::class);
        $subproject->SetProjectId($this->project->Id);
        $subproject->SetName($subprojectName);
        if ($subproject->GetId() < 1) {
            return null;
        }
        if (array_key_exists($subprojectName, $this->builds)) {
            return $this->builds[$subprojectName];
        }

        // Mark our default build as a parent and remove it from our list
        // of builds to save.
        // Make sure our parent build is marked as such.
        if (array_key_exists('', $this->builds)) {
            $this->parentBuild = $this->builds[''];
            unset($this->builds['']);
            $stmt = $this->pdo->prepare(
                'UPDATE build SET parentid = ? WHERE id = ?');
            pdo_execute($stmt, [Build::PARENT_BUILD, $this->parentBuild->Id]);
            $this->parentBuild->SetParentId(Build::PARENT_BUILD);
        }

        // Initialize the child build.
        $child_build = $this->getInstance(Build::class);
        $child_build->Generator = $this->parentBuild->Generator;
        $child_build->GroupId = $this->parentBuild->GroupId;
        $child_build->Name = $this->parentBuild->Name;
        $child_build->ProjectId = $this->parentBuild->ProjectId;
        $child_build->SiteId = $this->parentBuild->SiteId;
        $child_build->StartTime = $this->parentBuild->StartTime;
        $child_build->EndTime = $this->parentBuild->EndTime;
        $child_build->SubmitTime = gmdate(FMT_DATETIME);
        $child_build->SetParentId($this->parentBuild->Id);
        $child_build->SetStamp($this->parentBuild->GetStamp());
        $child_build->SetSubProject($subprojectName);
        $this->builds[$subprojectName] = $child_build;

        $this->buildErrors[$subprojectName] = [];
        $this->numTestsPassed[$subprojectName] = 0;
        $this->numTestsFailed[$subprojectName] = 0;
        $this->numTestsNotRun[$subprojectName] = 0;

        $this->initializeConfigure($child_build, $subprojectName);

        // Note that this has not been saved to the database yet
        // and does not have a valid buildid.
        return $child_build;
    }

    /**
     * Initialize a configure for a build.
     **/
    private function initializeConfigure($build, $subprojectName)
    {
        $configure = $this->getInstance(BuildConfigure::class);
        $configure->StartTime = $build->StartTime;
        $configure->EndTime = $build->EndTime;
        $configure->Log = '';
        $configure->Status = 0;
        $this->configures[$subprojectName] = $configure;
    }

    /**
     * @param $buildid
     * @param $test_status
     * @param $test_time
     * @param $test_name
     * @param $subprojectName
     */
    private function createNewTest($buildid, $test_status, $test_time, $test_name, $subprojectName)
    {
        $buildtest = $this->getInstance(BuildTest::class);
        $buildtest->BuildId = $buildid;
        $buildtest->Status = $test_status;
        $buildtest->Time = $test_time;

        $test = $this->getInstance(Test::class);
        $test->ProjectId = $this->project->Id;
        $test->Command = '';
        $test->Path = '';
        $test->Name = $test_name;
        if ($buildtest->Status === 'failed') {
            $this->numTestsFailed[$subprojectName]++;
            $test->Details = 'Completed (Failed)';
        } elseif ($buildtest->Status === 'timeout') {
            $buildtest->Status = 'failed';
            $this->numTestsFailed[$subprojectName]++;
            $test->Details = 'Completed (Timeout)';
            // "TIMEOUT" message is only in stderr, not stdout
            // Make sure that it is displayed.
            $this->testsOutput[$test->Name] = "TIMEOUT\n\n";
        } elseif (!empty($buildtest->Status)) {
            $this->numTestsPassed[$subprojectName]++;
            $test->Details = 'Completed';
        }

        // We will set this test's output (if any) before
        // inserting it into the database.
        $this->tests[] = [$test, $buildtest];
    }

    /**
     * @param $name
     * @return bool
     */
    private function isTestName($name)
    {
        foreach ($this->tests as $testdata) {
            $test = $testdata[0];
            if ($test->Name === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->builds);
    }
}
