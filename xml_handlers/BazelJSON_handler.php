<?php
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

require_once 'config/config.php';

use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildError;
use CDash\Model\BuildTest;
use CDash\Model\Project;
use CDash\Model\SubProject;
use CDash\Model\Test;

class BazelJSONHandler
{
    private $BuildId;
    private $Builds;
    private $BuildErrors;
    private $CommandLine;
    private $Configures;
    private $HasSubProjects;
    private $NumTestsPassed;
    private $NumTestsFailed;
    private $NumTestsNotRun;
    private $ParentBuild;
    private $Project;
    private $RecordingTestOutput;
    private $Tests;
    private $TestsOutput;
    private $TestName;
    private $WorkingDirectory;

    private $PDO;

    public function __construct($buildid)
    {
        $this->BuildId = $buildid;
        $this->Builds = [];
        $build = new Build();
        $build->Id = $buildid;
        $build->FillFromId($build->Id);
        $this->Builds[''] = $build;
        $this->ParentBuild = null;

        $this->Project = new Project();
        $this->Project->Id = $build->ProjectId;
        $this->Project->Fill();
        $this->HasSubProjects = $this->Project->GetNumberOfSubProjects() > 0;

        $this->CommandLine = '';
        $this->WorkingDirectory = '';

        $this->NumTestsPassed = [];
        $this->NumTestsFailed = [];
        $this->NumTestsNotRun = [];
        $this->NumTestsPassed[''] = 0;
        $this->NumTestsFailed[''] = 0;
        $this->NumTestsNotRun[''] = 0;

        $this->BuildErrors = ['' => []];
        $this->Configures = [];
        if (!$this->HasSubProjects) {
            $this->InitializeConfigure($build, '');
        }
        $this->RecordingTestOutput = false;
        $this->Tests = [];
        $this->TestsOutput = [];
        $this->TestName = '';

        $this->PDO = get_link_identifier()->getPdo();
    }

    /**
     * Parse a Bazel Build Event Procol .json file.
     **/
    public function Parse($filename)
    {
        $handle = fopen($filename, "r");
        if (!$handle) {
            add_log("Could not open $filename for parsing",
                    'BazelJSONHandler::Parse', LOG_ERR);
            return false;
        }

        while (($line = fgets($handle)) !== false) {
            $this->ParseLine($line);
        }
        fclose($handle);

        foreach ($this->Builds as $subproject_name => $build) {
            if ($this->HasSubProjects && $subproject_name == '') {
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
            $build->UpdateTestNumbers($num_passed, $num_failed, $num_notrun);
        }

        // Save configure information.
        foreach ($this->Configures as $subproject_name => $configure) {
            if ($this->HasSubProjects && $subproject_name == '') {
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
        foreach ($this->Tests as $testdata) {
            $test = $testdata[0];
            $buildtest = $testdata[1];

            if (array_key_exists($test->Name, $this->TestsOutput)) {
                $test->Output = $this->TestsOutput[$test->Name];
            }

            $test->Command = $this->CommandLine;
            $test->Insert();
            $test->InsertLabelAssociations($buildtest->BuildId);

            $buildtest->TestId = $test->Id;
            $buildtest->Insert();
        }
    }

    /**
     * Parse a single BEP line.
     **/
    public function ParseLine($line)
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
                if ($this->HasSubProjects) {
                    $target_name = $json_array['id']['pattern']['pattern'][0];
                    $subproject_name = SubProject::GetSubProjectForPath(
                            $target_name, $this->Project->Id);
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
                                $this->TestName = "";
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
                        } else {
                            if (preg_match($test_pattern, $line, $matches) === 1
                                    && count($matches) === 2) {
                                $this->TestName = $matches[1];
                                $this->RecordingTestOutput = true;
                            }
                        }
                    }
                }

                if (array_key_exists('stderr', $json_array[$message_id])) {
                    // Parse through stderr line-by-line,
                    // searching for build warnings and errors.
                    $stderr = $json_array[$message_id]['stderr'];
                    $warning_pattern = '/(.*?) warning: (.*?)$/';
                    $error_pattern = '/(.*?) error: (.*?)$/';
                    $configure_error_pattern = '/\s*ERROR: (.*?)BUILD/';
                    $configure_warning_pattern = '/\s*ERROR: (.*?)BUILD/';
                    $recording_configure_output = false;
                    $log_line_number = 1;
                    $lines = explode("\n", $stderr);
                    $build_error = null;
                    $subproject_name = '';

                    foreach ($lines as $line) {
                        // Remove ANSI color codes.
                        $line = preg_replace('/\033\[[0-9;]*m/', '', $line);

                        $record_configure_error = false;
                        $matches = [];
                        if (preg_match($configure_error_pattern, $line, $matches) === 1
                                && count($matches) === 2) {
                            // This line contains a configure error.
                            $record_configure_error = true;
                            $type = 0;
                        } elseif (preg_match($configure_warning_pattern, $line, $matches) === 1
                                && count($matches) === 2) {
                            // This line contains a configure warning.
                            $record_configure_error = true;
                            $type = 0;
                        }

                        if ($record_configure_error) {
                            $source_file = $matches[1] . "BUILD";
                            $subproject_name = '';
                            if ($this->HasSubProjects) {
                                $subproject_name = SubProject::GetSubProjectForPath(
                                        $source_file, $this->Project->Id);
                                // Skip this defect if we cannot deduce what SubProject
                                // it belongs to.
                                if (empty($subproject_name)) {
                                    continue;
                                }
                                $this->InitializeSubProjectBuild($subproject_name);
                            }
                            if ($type === 0) {
                                $this->Configures[$subproject_name]->Status += 1;
                            } else {
                                $this->Configures[$subproject_name]->NumberOfWarnings += 1;
                            }

                            // Capture subsequent lines as this configure's log.
                            $recording_configure_output = true;
                            continue;
                        }

                        $record_error = false;
                        if (preg_match($warning_pattern, $line, $matches) === 1
                                && count($matches) === 3) {
                            // This line contains a warning.
                            $record_error = true;
                            $type = 1;
                            $recording_configure_output = false;
                        } elseif (preg_match($error_pattern, $line, $matches) === 1
                                && count($matches) === 3) {
                            // This line contains an error.
                            $record_error = true;
                            $type = 0;
                            $recording_configure_output = false;
                        }

                        if ($recording_configure_output) {
                            $this->Configures[$subproject_name]->Log .= "$line\n";
                            continue;
                        }

                        if ($record_error) {
                            // Record any existing build error before creating
                            // a new one.
                            if (!is_null($build_error)) {
                                $this->BuildErrors[$subproject_name][] = $build_error;
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

                            $build_error->Text = $line;
                            $build_error->SourceFile = $source_file;
                            $build_error->SourceLine = $source_line_number;

                            // Look up the subproject (if any) that contains
                            // this source file.
                            $subproject_name = SubProject::GetSubProjectForPath(
                                $source_file, $this->Project->Id);
                            // Skip this defect if we cannot deduce what SubProject
                            // it belongs to.
                            if (empty($subproject_name)) {
                                continue;
                            }
                            $this->InitializeSubProjectBuild($subproject_name);
                        } elseif (!is_null($build_error)) {
                            // Record lines following the error/warning
                            // as post context.
                            $build_error->PostContext .= "$line\n";
                        }
                        $log_line_number++;
                    }
                    if (!is_null($build_error)) {
                        $this->BuildErrors[$subproject_name][] = $build_error;
                        $build_error = null;
                    }
                }
                break;

            case 'started':
                // Parse out the command line that created this file.
                $this->CommandLine = "bazel ";
                $this->CommandLine .= $json_array['started']['command'];
                $this->CommandLine .= $json_array['started']['optionsDescription'];

                $this->WorkingDirectory = $json_array['started']['workingDirectory'];
                break;

            case 'testResult':
                // By default, associate any tests with this->BuildId.
                $buildid = $this->BuildId;
                $subproject_name = '';
                if ($this->HasSubProjects) {
                    // But if this project is broken up into subprojects,
                    // we may want to assign this test to one of the children
                    // builds instead.
                    $target_name = $json_array['id']['testResult']['label'];
                    $subproject_name = SubProject::GetSubProjectForPath(
                            $target_name, $this->Project->Id);
                    // Skip this defect if we cannot deduce what SubProject
                    // it belongs to.
                    if (empty($subproject_name)) {
                        continue;
                    }
                    $child_build = $this->InitializeSubProjectBuild($subproject_name);
                    if (!is_null($child_build)) {
                        $child_build->InsertErrors = false;
                        add_build($child_build);
                        $buildid = $child_build->Id;
                    }
                }

                $buildtest = new BuildTest();
                $buildtest->BuildId = $buildid;
                $buildtest->Status =
                    strtolower($json_array['testResult']['status']);
                $buildtest->Time =
                    $json_array['testResult']['testAttemptDurationMillis'] / 1000.0;

                $test = new Test();
                $test->ProjectId = $this->Project->Id;
                $test->Command = '';
                $test->Path = '';
                $test->Name = $json_array['id']['testResult']['label'];
                if ($buildtest->Status === 'failed') {
                    $this->NumTestsFailed[$subproject_name]++;
                    $test->Details = 'Completed (Failed)';
                } elseif ($buildtest->Status === 'timeout') {
                    $buildtest->Status = 'failed';
                    $this->NumTestsFailed[$subproject_name]++;
                    $test->Details = 'Completed (Timeout)';
                    // "TIMEOUT" message is only in stderr, not stdout
                    // Make sure that it is displayed.
                    $this->TestsOutput[$test->Name] = "TIMEOUT\n";
                } else {
                    $this->NumTestsPassed[$subproject_name]++;
                    $test->Details = 'Completed';
                }
                // We will set this test's output (if any) before inserting it into the database.

                $this->Tests[] = [$test, $buildtest];

                break;

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
        $subproject = new SubProject();
        $subproject->SetProjectId($this->Project->Id);
        $subproject->SetName($subproject_name);
        if ($subproject->GetId() < 1) {
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
        $child_build->GroupId = $this->ParentBuild->GroupId;
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
    private function InitializeConfigure($build, $subproject_name)
    {
        $configure = new BuildConfigure();
        $configure->StartTime = $build->StartTime;
        $configure->EndTime = $build->EndTime;
        $configure->Log = '';
        $configure->Status = 0;
        $this->Configures[$subproject_name] = $configure;
    }
}
