<?php

namespace App\Http\Submission\Handlers;

/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

use App\Models\Project as EloquentProject;
use App\Utils\SubmissionUtils;
use App\Utils\TestCreator;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildError;
use CDash\Model\BuildErrorFilter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use stdClass;

class BazelJSONHandler extends AbstractSubmissionHandler
{
    private $Builds;
    private $BuildErrors;
    private $CommandLine;
    private array $Configures = [];
    private ?bool $_HasSubProjects = null;
    private $NumTestsPassed;
    private $NumTestsFailed;
    private $NumTestsNotRun;
    private $ParentBuild;
    private $RecordingTestOutput;
    private $RecordingTestSummary;
    private $Tests;
    private $TestsOutput;
    private $TestName;
    private $ParseConfigure;
    private ?BuildErrorFilter $BuildErrorFilter = null;

    private $PDO;

    public function __construct(Build $build)
    {
        parent::__construct($build);

        $this->Builds = [];
        $this->Builds[''] = $this->Build;
        $this->ParentBuild = null;

        $this->CommandLine = '';

        $this->NumTestsPassed = [];
        $this->NumTestsFailed = [];
        $this->NumTestsNotRun = [];
        $this->NumTestsPassed[''] = 0;
        $this->NumTestsFailed[''] = 0;
        $this->NumTestsNotRun[''] = 0;

        $this->BuildErrors = ['' => []];
        $this->RecordingTestOutput = false;
        $this->RecordingTestSummary = false;
        $this->Tests = [];
        $this->TestsOutput = [];
        $this->TestName = '';
        $this->ParseConfigure = true;

        $this->PDO = Database::getInstance()->getPdo();
    }

    protected function HasSubProjects(): bool
    {
        if ($this->_HasSubProjects === null) {
            $this->_HasSubProjects = $this->GetProject()->GetNumberOfSubProjects() > 0;
        }
        return $this->_HasSubProjects;
    }

    /**
     * Parse a Bazel Build Event Procol .json file.
     **/
    public function Parse($filename)
    {
        $handle = Storage::readStream($filename);
        if ($handle === null) {
            Log::error("Could not open $filename for parsing", [
                'function' => 'BazelJSONHandler::Parse',
            ]);
            return false;
        }

        while (($line = fgets($handle)) !== false) {
            $this->ParseLine($line);
        }
        fclose($handle);

        foreach ($this->Builds as $subproject_name => $build) {
            if ($this->HasSubProjects() && $subproject_name == '') {
                // Skip this build if it isn't associated with a SubProject
                // and it should be.
                continue;
            }

            // Record any build errors that were found.
            $build->InsertErrors = true;
            foreach ($this->BuildErrors[$subproject_name] as $builderror) {
                $build->AddError($builderror);
            }
            $build->Save();

            // Update number of tests in the build table.
            $num_passed = $build->GetNumberOfPassedTests() +
                $this->NumTestsPassed[$subproject_name];
            $num_failed = $build->GetNumberOfFailedTests() +
                $this->NumTestsFailed[$subproject_name];
            $num_notrun = $build->GetNumberOfNotRunTests() +
                $this->NumTestsNotRun[$subproject_name];
            $build->UpdateTestNumbers((int) $num_passed, (int) $num_failed, (int) $num_notrun);
        }

        if (!$this->HasSubProjects()) {
            $this->InitializeConfigure($this->Build, '');
        }

        // Save configure information.
        foreach ($this->Configures as $subproject_name => $configure) {
            if ($this->HasSubProjects() && $subproject_name == '') {
                // Skip this configure if it isn't associated with a SubProject
                // and it should be.
                continue;
            }
            $build = $this->Builds[$subproject_name];
            $configure->BuildId = $build->Id;
            $configure->Command = $this->CommandLine;

            // Only record this configuration if it doesn't already exist.
            // Otherwise we will potentially repeat the same errors.
            if (!$configure->ExistsByBuildId()) {
                if ($configure->Insert()) {
                    $configure->ComputeWarnings();
                    $configure->ComputeErrors();
                }

                // Record the number of warnings & errors with the build.
                $build->SetNumberOfConfigureWarnings(
                    (int) $configure->NumberOfWarnings);
                $build->SetNumberOfConfigureErrors(
                    (int) $configure->NumberOfErrors);
                $build->ComputeConfigureDifferences();

                // Update the tally of warnings & errors in the parent build,
                // if applicable.
                if (!empty($subproject_name)) {
                    $build->UpdateParentConfigureNumbers(
                        (int) $configure->NumberOfWarnings, (int) $configure->NumberOfErrors);
                }
            }
        }

        // Save testing information.
        foreach ($this->Tests as $testdata) {
            $testCreator = new TestCreator();

            $testCreator->buildTestTime = $testdata->time;
            $testCreator->projectid = $this->GetProject()->Id;
            $testCreator->testCommand = $this->CommandLine;
            $testCreator->testDetails = $testdata->details;
            $testCreator->testName = $testdata->name;
            $testCreator->testStatus = $testdata->status;

            if (array_key_exists($testdata->name, $this->TestsOutput)) {
                $testCreator->testOutput = $this->TestsOutput[$testdata->name];
            }

            foreach ($this->Builds as $subproject_name => $build) {
                if ($build->Id = $testdata->buildid) {
                    $testCreator->create($build);
                    break;
                }
            }
        }

        // Save testdiff information.
        foreach ($this->Builds as $subproject_name => $build) {
            $build->ComputeTestTiming();
        }
    }

    /**
     * Parse a single BEP line.
     **/
    public function ParseLine($line)
    {
        $json_array = json_decode($line, true);
        if (is_null($json_array)) {
            Log::error('json_decode error: ' . json_last_error_msg(), [
                'function' => 'BazelJSONHandler::ParseLine',
            ]);
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
                if ($this->HasSubProjects()) {
                    $target_name = $json_array['id']['pattern']['pattern'][0];
                    $subproject_name = self::GetSubProjectForPath(
                        $target_name, intval($this->GetProject()->Id));
                    if (!empty($subproject_name)) {
                        $this->InitializeSubProjectBuild($subproject_name);
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
                        if ($this->RecordingTestOutput) {
                            if (preg_match('/={80}/', $line)) {
                                // A line of exacty 80 '='s means the end of output
                                // for this test.
                                $this->RecordingTestOutput = false;
                                $this->TestName = '';
                            } else {
                                if (!array_key_exists(
                                    $this->TestName, $this->TestsOutput)) {
                                    $this->TestsOutput[$this->TestName] = $line;
                                    $continue_line = false;
                                } elseif (!empty($line) && $continue_line) {
                                    // Continue line from previous 'progress' event
                                    $this->TestsOutput[$this->TestName] .= "$line";
                                    $continue_line = false;
                                } else {
                                    $this->TestsOutput[$this->TestName] .= "\n$line";
                                }
                            }
                        } elseif ($this->RecordingTestSummary) {
                            $begin_line = explode(' ', $line)[0];
                            if ($begin_line === 'Executed') {
                                // The summary of all tests begins with
                                // "Executed"
                                $this->RecordingTestSummary = false;
                                $this->TestName = '';
                            } elseif ($this->IsTestName($begin_line)) {
                                // Check if this line starts with a test name
                                // (might be a different test than the one we're
                                // currently processing).
                                $this->TestName = $begin_line;
                                if (!array_key_exists(
                                    $this->TestName, $this->TestsOutput)) {
                                    $this->TestsOutput[$this->TestName] = $line;
                                } else {
                                    $this->TestsOutput[$this->TestName] .= "\n\n$line";
                                }
                            } else {
                                // Add output to current test
                                $this->TestsOutput[$this->TestName] .= "\n\n$line";
                            }
                        } elseif (preg_match($test_pattern, $line, $matches) === 1
                                    && count($matches) === 2) {
                            // For sharded tests, this string will be:
                            // '<test name> (shard <n> of <total>)'. Split
                            // off just the <test name> part.
                            $this->TestName = explode(' ', $matches[1])[0];
                            $this->RecordingTestOutput = true;
                        } else {
                            // Check if this line starts with a test name
                            $test_name = explode(' ', $line)[0];
                            if (array_key_exists($test_name, $this->TestsOutput)) {
                                $this->RecordingTestSummary = true;
                                $this->TestName = $test_name;
                                if (!array_key_exists(
                                    $this->TestName, $this->TestsOutput)) {
                                    $this->TestsOutput[$this->TestName] = $line;
                                } else {
                                    $this->TestsOutput[$this->TestName] .= "\n\n$line";
                                }
                            }
                        }
                    }
                }

                if (array_key_exists('stderr', $json_array[$message_id])) {
                    // Parse through stderr line-by-line,
                    // searching for configure and build warnings and errors.

                    // The first two phases of a Bazel build, Loading and
                    // Analysis, will be treated as the 'Configure' step by
                    // CDash. The final phase, Execution, will be treated as
                    // the 'Build' step by CDash.

                    $stderr = $json_array[$message_id]['stderr'];

                    $info_pattern = '/(.*?)INFO: (.*?)$/';
                    $linking_pattern = '/(.*?)____From Linking (.*?)$/';
                    $error_pattern = '/(.*?)ERROR: (.*?)$/';
                    $warning_pattern = '/(.*?)warning: (.*?)$/';

                    $configure_error_pattern = '/\s*ERROR: (.*?)BUILD/';
                    $analysis_warning_pattern = '/\s*WARNING: (.*?)$/';
                    // Look for the report printed at the end of the analysis phase.
                    $analysis_report_pattern = '/(.*?)Found (.*?)target(.*?)/';

                    $log_line_number = 1;
                    $lines = explode("\n", $stderr);
                    $build_error = null;
                    $subproject_name = '';
                    $warning_text = '';
                    $check_for_warning = false;

                    foreach ($lines as $line) {
                        // Remove ANSI color codes.
                        $line = preg_replace('/\033\[[0-9;]*m/', '', $line);

                        $matches = [];

                        // Check if we're done with the 'Configure' step
                        if (preg_match($analysis_report_pattern, $line, $matches) === 1) {
                            $this->ParseConfigure = false;
                        }

                        $record_configure_error = false;
                        if ($this->ParseConfigure) {
                            if (preg_match($analysis_warning_pattern, $line, $matches) === 1
                                    && count($matches) === 2) {
                                $source_file = str_replace(':', '/', $matches[1]);
                                $record_configure_error = true;
                                $type = 1;
                            } elseif (preg_match($configure_error_pattern, $line, $matches) === 1
                                    && count($matches) === 2) {
                                $source_file = $matches[1] . 'BUILD';
                                $record_configure_error = true;
                                $type = 0;
                            }
                            if ($record_configure_error) {
                                $subproject_name = '';
                                if ($this->HasSubProjects()) {
                                    $subproject_name = self::GetSubProjectForPath(
                                        $source_file, intval($this->GetProject()->Id));
                                    // Skip this defect if we cannot deduce what SubProject
                                    // it belongs to.
                                    if (empty($subproject_name)) {
                                        continue;
                                    }
                                    $this->InitializeSubProjectBuild($subproject_name);
                                } else {
                                    $this->InitializeConfigure($this->Build, '');
                                }

                                if ($type === 0) {
                                    // We don't know what the status should be,
                                    // other than non-zero. Use 1 as a reasonable
                                    // default.
                                    $this->Configures[$subproject_name]->Status = 1;
                                    $this->Configures[$subproject_name]->NumberOfErrors++;
                                } else {
                                    $this->Configures[$subproject_name]->NumberOfWarnings++;
                                }

                                // Capture line as this configure's log.
                                $this->Configures[$subproject_name]->Log .= "$line\n";
                            }
                        } else {
                            // Done with configure, parsing build errors and warnings
                            $record_error = false;

                            if ($check_for_warning) {
                                if (preg_match($warning_pattern, $line, $matches) === 1
                                      && count($matches) === 3) {
                                    // This line is part of a warning message
                                    $record_error = true;
                                    $type = 1;
                                } else {
                                    $warning_text = '';
                                }
                                $check_for_warning = false;
                            }

                            if (!empty($warning_text)) {
                                // This line is part of a warning message
                            } elseif (preg_match($info_pattern, $line, $matches) === 1
                                  && count($matches) === 3) {
                                // This line might be the start of a warning message
                                // Some warnings are structured:
                                // INFO: ....
                                // ... warning: ...
                                // ...
                                // ... warning: ...
                                // ...
                                // <n> warnings generated.
                                $check_for_warning = true;
                                $warning_text = $line;
                            } elseif (preg_match($linking_pattern, $line, $matches) === 1
                                  && count($matches) === 3) {
                                // This line might be the start of a warning message
                                // Some warnings are structed:
                                // ____From Linking <...>:
                                // clang: warning: ...
                                $check_for_warning = true;
                                $warning_text = $line;
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
                                    $this->RecordError($build_error, $type, $subproject_name);
                                    $subproject_name = '';
                                    $build_error = null;
                                }

                                $build_error = new BuildError();
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

                                if (empty($warning_text)) {
                                    $build_error->Text = $line;
                                } else {
                                    $build_error->Text = $warning_text;
                                    $warning_text = '';
                                    $build_error->PostContext .= "$line\n";
                                }
                                $build_error->SourceFile = $source_file;
                                $build_error->SourceLine = $source_line_number;

                                $subproject_name = '';
                                if ($this->HasSubProjects()) {
                                    // Look up the subproject (if any) that contains
                                    // this source file.
                                    $subproject_name = self::GetSubProjectForPath(
                                        $source_file, intval($this->GetProject()->Id));
                                    // Skip this defect if we cannot deduce what SubProject
                                    // it belongs to.
                                    if (empty($subproject_name)) {
                                        continue;
                                    }
                                    $this->InitializeSubProjectBuild($subproject_name);
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
                        $this->RecordError($build_error, $type, $subproject_name);
                        $build_error = null;
                    }
                }
                break;

            case 'started':
                // Parse out the command line that created this file.
                $this->CommandLine = 'bazel ';
                $this->CommandLine .= $json_array['started']['command'];
                if (array_key_exists('optionsDescription', $json_array['started'])) {
                    $this->CommandLine .= $json_array['started']['optionsDescription'];
                }
                break;

            case 'testResult':
                // Skip test results with children, the output is duplicated
                if (!array_key_exists('children', $json_array)) {
                    // By default, associate any tests with this->BuildId.
                    $buildid = $this->Build->Id;
                    $subproject_name = '';
                    if ($this->HasSubProjects()) {
                        // But if this project is broken up into subprojects,
                        // we may want to assign this test to one of the children
                        // builds instead.
                        $target_name = $json_array['id']['testResult']['label'];
                        $subproject_name = self::GetSubProjectForPath(
                            $target_name, intval($this->GetProject()->Id));
                        // Skip this defect if we cannot deduce what SubProject
                        // it belongs to.
                        if (empty($subproject_name)) {
                            break;
                        }
                        $child_build = $this->InitializeSubProjectBuild($subproject_name);
                        if (!is_null($child_build)) {
                            $child_build->InsertErrors = false;
                            SubmissionUtils::add_build($child_build);
                            $buildid = $child_build->Id;
                        }
                    }

                    $test_name = $json_array['id']['testResult']['label'];
                    $test_time = 0;
                    if (array_key_exists('testResult', $json_array) && array_key_exists('testAttemptDurationMillis', $json_array['testResult'])) {
                        $test_time = $json_array['testResult']['testAttemptDurationMillis'] / 1000.0;
                    } elseif (array_key_exists('testAttemptDurationMillis', $json_array['id']['testResult'])) {
                        $test_time = $json_array['id']['testResult']['testAttemptDurationMillis'] / 1000.0;
                    }

                    if (array_key_exists('shard', $json_array['id']['testResult'])) {
                        // This test uses shards, so a Test with this name
                        // might already exist
                        $new_test = true;
                        foreach ($this->Tests as $testdata) {
                            if ($testdata->name === $test_name) {
                                // Increment test time
                                $testdata->time += $test_time;
                                $new_test = false;
                                break;
                            }
                        }
                        if ($new_test) {
                            // We'll set the overall test status from 'testSummary'
                            $this->CreateNewTest($buildid, '',
                                $test_time, $test_name, $subproject_name);
                        }
                    } else {
                        $test_status = strtolower($json_array['testResult']['status']);
                        $this->CreateNewTest($buildid, $test_status,
                            $test_time, $test_name, $subproject_name);
                    }
                }
                break;
            case 'testSummary':
                // By default, associate any tests with this->BuildId.
                $subproject_name = '';
                if ($this->HasSubProjects()) {
                    // But if this project is broken up into subprojects,
                    // we may want to assign this test to one of the children
                    // builds instead.
                    $target_name = $json_array['id']['testSummary']['label'];
                    $subproject_name = self::GetSubProjectForPath(
                        $target_name, intval($this->GetProject()->Id));
                    // Skip this defect if we cannot deduce what SubProject
                    // it belongs to.
                    if (empty($subproject_name)) {
                        break;
                    }
                }
                $test_name = $json_array['id']['testSummary']['label'];
                foreach ($this->Tests as $testdata) {
                    if ($testdata->name === $test_name) {
                        if (!array_key_exists('testSummary', $json_array)) {
                            continue;
                        }
                        $testdata->status =
                            strtolower($json_array['testSummary']['overallStatus']);
                        if ($testdata->status === 'passed' || $testdata->status === 'flaky') {
                            $this->NumTestsPassed[$subproject_name]++;
                            $testdata->status = 'passed';
                            $testdata->details = 'Completed';
                        } elseif ($testdata->status === 'timeout') {
                            $this->NumTestsFailed[$subproject_name]++;
                            $testdata->status = 'failed';
                            $testdata->details = 'Completed (Timeout)';
                            // "TIMEOUT" message is only in stderr, not stdout
                            // Make sure that it is displayed.
                            $this->TestsOutput[$testdata->name] = "TIMEOUT\n\n";
                        } elseif (!empty($testdata->status)) {
                            // "failed", "flaky", etc...  See here for the set of possible values:
                            // https://github.com/bazelbuild/bazel/blob/5b2baea3d70e2e1381bc6dbfb0327130d00d98ee/src/main/java/com/google/devtools/build/lib/buildeventstream/proto/build_event_stream.proto#L676
                            $this->NumTestsFailed[$subproject_name]++;
                            $testdata->details = 'Completed (Failed)';
                        }
                        break;
                    }
                }
                // no break
            default:
                break;
        }
    }

    /**
     * Initialize a build for the given subproject.
     * Return the build or null if the subproject could not be found.
     **/
    private function InitializeSubProjectBuild($subproject_name)
    {
        if (empty($subproject_name)) {
            return null;
        }

        $subproject_exists = EloquentProject::findOrFail($this->GetProject()->Id)
            ->subprojects()
            ->where('name', $subproject_name)
            ->exists();

        if (!$subproject_exists) {
            return null;
        }

        if (array_key_exists($subproject_name, $this->Builds)) {
            return $this->Builds[$subproject_name];
        }

        // Mark our default build as a parent and remove it from our list
        // of builds to save.
        // Make sure our parent build is marked as such.
        if (array_key_exists('', $this->Builds)) {
            $this->ParentBuild = $this->Builds[''];
            unset($this->Builds['']);
            $stmt = $this->PDO->prepare(
                'UPDATE build SET parentid = ? WHERE id = ?');
            pdo_execute($stmt, [Build::PARENT_BUILD, $this->ParentBuild->Id]);
            $this->ParentBuild->SetParentId(Build::PARENT_BUILD);
        }

        // Initialize the child build.
        $child_build = new Build();
        $child_build->Generator = $this->ParentBuild->Generator;
        $child_build->GroupId = (int) $this->ParentBuild->GroupId;
        $child_build->Name = $this->ParentBuild->Name;
        $child_build->ProjectId = $this->ParentBuild->ProjectId;
        $child_build->SiteId = $this->ParentBuild->SiteId;
        $child_build->StartTime = $this->ParentBuild->StartTime;
        $child_build->EndTime = $this->ParentBuild->EndTime;
        $child_build->SubmitTime = gmdate(FMT_DATETIME);
        $child_build->SetParentId($this->ParentBuild->Id);
        $child_build->SetStamp($this->ParentBuild->GetStamp());
        $child_build->SetSubProject($subproject_name);
        $this->Builds[$subproject_name] = $child_build;

        $this->BuildErrors[$subproject_name] = [];
        $this->NumTestsPassed[$subproject_name] = 0;
        $this->NumTestsFailed[$subproject_name] = 0;
        $this->NumTestsNotRun[$subproject_name] = 0;

        $this->InitializeConfigure($child_build, $subproject_name);

        // Note that this has not been saved to the database yet
        // and does not have a valid buildid.
        return $child_build;
    }

    /**
     * Initialize a configure for a build.
     **/
    private function InitializeConfigure($build, $subproject_name): void
    {
        if (array_key_exists($subproject_name, $this->Configures)) {
            return;
        }

        $configure = new BuildConfigure();
        $configure->StartTime = $build->StartTime;
        $configure->EndTime = $build->EndTime;
        $configure->Log = '';
        $configure->Status = 0;
        $this->Configures[$subproject_name] = $configure;
    }

    private function CreateNewTest($buildid, $test_status, $test_time, $test_name, $subproject_name)
    {
        $testdata = new stdClass();
        $testdata->buildid = $buildid;
        $testdata->name = $test_name;
        $testdata->status = $test_status;
        $testdata->time = $test_time;
        $testdata->details = '';

        if ($testdata->status === 'passed' || $testdata->status === 'flaky') {
            $this->NumTestsPassed[$subproject_name]++;
            $testdata->status = 'passed';
            $testdata->details = 'Completed';
        } elseif ($testdata->status === 'timeout') {
            $this->NumTestsFailed[$subproject_name]++;
            $testdata->status = 'failed';
            $testdata->details = 'Completed (Timeout)';
            // "TIMEOUT" message is only in stderr, not stdout
            // Make sure that it is displayed.
            $this->TestsOutput[$testdata->name] = "TIMEOUT\n\n";
        } elseif (!empty($testdata->status)) {
            // "failed", "flaky", etc...  See here for the set of possible values:
            // https://github.com/bazelbuild/bazel/blob/5b2baea3d70e2e1381bc6dbfb0327130d00d98ee/src/main/java/com/google/devtools/build/lib/buildeventstream/proto/build_event_stream.proto#L676
            $this->NumTestsFailed[$subproject_name]++;
            $testdata->details = 'Completed (Failed)';
        }

        $this->Tests[] = $testdata;
    }

    private function IsTestName($name)
    {
        foreach ($this->Tests as $testdata) {
            if ($testdata->name === $name) {
                return true;
            }
        }
        return false;
    }

    public function getBuild(): Build
    {
        if (count($this->Builds) > 1) {
            $build = new Build();
            $build->Id = array_values($this->Builds)[0]->GetParentId();
            return $build;
        } else {
            return array_values($this->Builds)[0];
        }
    }

    private function RecordError($build_error, $type, $subproject_name)
    {
        $text_with_context = $build_error->Text . $build_error->PostContext;

        if ($this->BuildErrorFilter === null) {
            $this->BuildErrorFilter = new BuildErrorFilter($this->GetProject());
            $this->BuildErrorFilter->Fill();
        }

        if ($type === 0) {
            $skip_error = $this->BuildErrorFilter->FilterError($text_with_context);
        } else {
            $skip_error = $this->BuildErrorFilter->FilterWarning($text_with_context);
        }
        if (!$skip_error) {
            $this->BuildErrors[$subproject_name][] = $build_error;
        }
    }

    /**
     * Return the name of the subproject whose path contains the specified
     * source file.
     */
    private static function GetSubProjectForPath(string $filepath, int $projectid): string
    {
        $pdo = Database::getInstance()->getPdo();
        // Get all the subprojects for this project that have a path defined.
        // Sort by longest paths first.
        $stmt = $pdo->prepare(
            "SELECT name, path FROM subproject
            WHERE projectid = ? AND path != ''
            ORDER BY CHAR_LENGTH(path) DESC");
        pdo_execute($stmt, [$projectid]);
        while ($row = $stmt->fetch()) {
            // Return the name of the subproject with the longest path
            // that matches our input path.
            if (str_contains($filepath, $row['path'])) {
                return $row['name'];
            }
        }

        // Return empty string if no match was found.
        return '';
    }
}
