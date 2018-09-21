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

require_once 'xml_handlers/abstract_handler.php';
require_once 'xml_handlers/actionable_build_interface.php';

use CDash\Config;
use CDash\Model\Build;
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;
use CDash\Model\BuildInformation;
use CDash\Model\Feed;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

class BuildHandler extends AbstractHandler implements ActionableBuildInterface
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Error;
    private $Label;
    private $Append;
    private $Feed;
    private $Builds;
    private $BuildInformation;
    private $BuildCommand;
    private $BuildLog;
    private $Labels;
    // Map SubProjects to Labels
    private $SubProjects;
    private $ErrorSubProjectName;
    private $BuildName;
    private $BuildStamp;
    private $Generator;
    private $PullRequest;

    public function __construct($projectid, $scheduleid)
    {
        parent::__construct($projectid, $scheduleid);
        $this->Builds = [];
        $this->Site = new Site();
        $this->Append = false;
        $this->Feed = new Feed();
        $this->BuildLog = '';
        $this->Labels = [];
        $this->SubProjects = [];
    }

    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);

        if ($name == 'SITE') {
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();

            $siteInformation = new SiteInformation();
            $this->BuildInformation = new BuildInformation();
            $this->BuildName = "";
            $this->BuildStamp = "";
            $this->Generator = "";
            $this->PullRequest = "";

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                if ($key === 'BUILDNAME') {
                    $this->BuildName = $value;
                } elseif ($key === 'BUILDSTAMP') {
                    $this->BuildStamp = $value;
                } elseif ($key === 'GENERATOR') {
                    $this->Generator = $value;
                } elseif ($key == 'CHANGEID') {
                    $this->PullRequest = $value;
                } else {
                    $siteInformation->SetValue($key, $value);
                    $this->BuildInformation->SetValue($key, $value);
                }
            }

            if (empty($this->BuildName)) {
                $this->BuildName = '(empty)';
            }

            $this->Site->SetInformation($siteInformation);

            if (array_key_exists('APPEND', $attributes)) {
                if (strtolower($attributes['APPEND']) == 'true') {
                    $this->Append = true;
                }
            } else {
                $this->Append = false;
            }
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = array();
            }
            if (!array_key_exists($this->SubProjectName, $this->Builds)) {
                $build = new Build();
                if (!empty($this->PullRequest)) {
                    $build->SetPullRequest($this->PullRequest);
                }
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildName;
                $build->SetStamp($this->BuildStamp);
                $build->Generator = $this->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[$this->SubProjectName] = $build;
            }
        } elseif ($name == 'BUILD') {
            if (empty($this->Builds)) {
                // No subprojects
                $build = new Build();
                if (!empty($this->PullRequest)) {
                    $build->SetPullRequest($this->PullRequest);
                }
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildName;
                $build->SetStamp($this->BuildStamp);
                $build->Generator = $this->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[''] = $build;
            }
        } elseif ($name == 'WARNING') {
            $this->Error = new BuildError();
            $this->Error->Type = 1;
            $this->ErrorSubProjectName = "";
        } elseif ($name == 'ERROR') {
            $this->Error = new BuildError();
            $this->Error->Type = 0;
            $this->ErrorSubProjectName = "";
        } elseif ($name == 'FAILURE') {
            $this->Error = new BuildFailure();
            $this->Error->Type = 0;
            if ($attributes['TYPE'] == 'Error') {
                $this->Error->Type = 0;
            } elseif ($attributes['TYPE'] == 'Warning') {
                $this->Error->Type = 1;
            }
            $this->ErrorSubProjectName = "";
        } elseif ($name == 'LABEL') {
            $this->Label = new Label();
        }
    }

    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($name == 'BUILD') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            $submit_time = gmdate(FMT_DATETIME);
            // Do not add each build's duration to the parent's tally if this
            // XML file represents multiple "all-at-once" SubProject builds.
            $all_at_once = count($this->Builds) > 1;
            $parent_duration_set = false;
            foreach ($this->Builds as $subproject => $build) {
                $build->ProjectId = $this->projectid;
                $build->StartTime = $start_time;
                $build->EndTime = $end_time;
                $build->SubmitTime = $submit_time;
                if (empty($subproject)) {
                    $build->SetSubProject($this->SubProjectName);
                } else {
                    $build->SetSubProject($subproject);
                }
                $build->Append = $this->Append;
                $build->Command = $this->BuildCommand;
                $build->Log .= $this->BuildLog;

                foreach ($this->Labels as $label) {
                    $build->AddLabel($label);
                }
                add_build($build, $this->scheduleid);

                $duration = $this->EndTimeStamp - $this->StartTimeStamp;
                $build->UpdateBuildDuration($duration, !$all_at_once);
                if ($all_at_once && !$parent_duration_set) {
                    $parent_build = new Build();
                    $parent_build->Id = $build->GetParentId();
                    $parent_build->UpdateBuildDuration($duration, false);
                    $parent_duration_set = true;
                }

                $build->ComputeDifferences();

                if ($this->config->get('CDASH_ENABLE_FEED')) {
                    // Insert the build into the feed
                    $this->Feed->InsertBuild($this->projectid, $build->Id);
                }
            }
        } elseif ($name == 'WARNING' || $name == 'ERROR' || $name == 'FAILURE') {
            $threshold = $this->config->get('CDASH_LARGE_TEXT_LIMIT');

            if ($threshold > 0 && isset($this->Error->StdOutput)) {
                $chunk_size = $threshold / 2;
                $outlen = strlen($this->Error->StdOutput);
                if ($outlen > $threshold) {
                    $beginning = substr($this->Error->StdOutput, 0, $chunk_size);
                    $end = substr($this->Error->StdOutput, -$chunk_size);
                    unset($this->Error->StdOutput);
                    $this->Error->StdOutput =
                        "$beginning\n...\nCDash truncated output because it exceeded $threshold characters.\n...\n$end\n";
                    $outlen = strlen($this->Error->StdOutput);
                }

                $errlen = strlen($this->Error->StdError);
                if ($errlen > $threshold) {
                    $beginning = substr($this->Error->StdError, 0, $chunk_size);
                    $end = substr($this->Error->StdError, -$chunk_size);
                    unset($this->Error->StdError);
                    $this->Error->StdError =
                        "$beginning\n...\nCDash truncated output because it exceeded $threshold characters.\n...\n$end\n";
                    $errlen = strlen($this->Error->StdError);
                }
            }
            if (array_key_exists($this->SubProjectName, $this->Builds)) {
                $this->Builds[$this->SubProjectName]->AddError($this->Error);
            }
            unset($this->Error);
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            if (!empty($this->ErrorSubProjectName)) {
                $this->SubProjectName = $this->ErrorSubProjectName;
            } elseif (isset($this->Error)) {
                $this->Error->AddLabel($this->Label);
            } else {
                $this->Labels[] = $this->Label;
            }
        }
    }

    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent == 'BUILD') {
            switch ($element) {
                case 'STARTBUILDTIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'ENDBUILDTIME':
                    $this->EndTimeStamp = $data;
                    break;
                case 'BUILDCOMMAND':
                    $this->BuildCommand = htmlspecialchars_decode($data);
                    break;
                case 'LOG':
                    $this->BuildLog .= htmlspecialchars_decode($data);
                    break;
            }
        } elseif ($parent == 'ACTION') {
            switch ($element) {
                case 'LANGUAGE':
                    $this->Error->Language .= $data;
                    break;
                case 'SOURCEFILE':
                    $this->Error->SourceFile .= $data;
                    break;
                case 'TARGETNAME':
                    $this->Error->TargetName .= $data;
                    break;
                case 'OUTPUTFILE':
                    $this->Error->OutputFile .= $data;
                    break;
                case 'OUTPUTTYPE':
                    $this->Error->OutputType .= $data;
                    break;
            }
        } elseif ($parent == 'COMMAND') {
            switch ($element) {
                case 'WORKINGDIRECTORY':
                    $this->Error->WorkingDirectory .= $data;
                    break;
                case 'ARGUMENT':
                    $this->Error->AddArgument($data);
                    break;
            }
        } elseif ($parent == 'RESULT') {
            $threshold = $this->config->get('CDASH_LARGE_TEXT_LIMIT');
            $append = true;

            switch ($element) {
                case 'STDOUT':
                    if ($threshold > 0) {
                        if (strlen($this->Error->StdOutput) > $threshold) {
                            $append = false;
                        }
                    }

                    if ($append) {
                        $this->Error->StdOutput .= $data;
                    }
                    break;

                case 'STDERR':
                    if ($threshold > 0) {
                        if (strlen($this->Error->StdError) > $threshold) {
                            $append = false;
                        }
                    }

                    if ($append) {
                        $this->Error->StdError .= $data;
                    }
                    break;

                case 'EXITCONDITION':
                    $this->Error->ExitCondition .= $data;
                    break;
            }
        } elseif ($element == 'BUILDLOGLINE') {
            $this->Error->LogLine .= $data;
        } elseif ($element == 'TEXT') {
            $this->Error->Text .= $data;
        } elseif ($element == 'SOURCEFILE') {
            $this->Error->SourceFile .= $data;
        } elseif ($element == 'SOURCELINENUMBER') {
            $this->Error->SourceLine .= $data;
        } elseif ($element == 'PRECONTEXT') {
            $this->Error->PreContext .= $data;
        } elseif ($element == 'POSTCONTEXT') {
            $this->Error->PostContext .= $data;
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->SubProjects[$this->SubProjectName][] =  $data;
        } elseif ($parent == 'LABELS' && $element == 'LABEL') {
            // First, check if this label belongs to a SubProject
            foreach ($this->SubProjects as $subproject => $labels) {
                if (in_array($data, $labels)) {
                    $this->ErrorSubProjectName = $subproject;
                    break;
                }
            }
            if (empty($this->ErrorSubProjectName)) {
                $this->Label->SetText($data);
            }
        }
    }

    public function getBuildStamp()
    {
        return $this->BuildStamp;
    }

    public function getBuildName()
    {
        return $this->BuildName;
    }

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->Builds);
    }
    public function getActionableBuilds()
    {
        return $this->Builds;
    }
}
