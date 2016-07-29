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
require_once 'models/build.php';
require_once 'models/label.php';
require_once 'models/site.php';
require_once 'models/buildfailure.php';
require_once 'models/feed.php';

class BuildHandler extends AbstractHandler
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

    public function __construct($projectid, $scheduleid)
    {
        parent::__construct($projectid, $scheduleid);
        $this->Builds = array();
        $this->Site = new Site();
        $this->Append = false;
        $this->Feed = new Feed();
        $this->BuildLog = '';
        $this->Labels = array();
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

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $this->BuildInformation->SetValue($key, $value);
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
            if (!array_key_exists($this->SubProjectName, $this->Builds)) {
                $subprojectName = $attributes['NAME'];
                $build = new Build();
                if (!empty($this->BuildInformation->PullRequest)) {
                    $build->SetPullRequest($value);
                }
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildInformation->BuildName;
                $build->SetStamp($this->BuildInformation->BuildStamp);
                $build->Generator = $this->BuildInformation->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[$subprojectName] = $build;
            }
        } elseif ($name == 'BUILD') {
            if (empty($this->Builds)) {
                // No subprojects
                $build = new Build();
                if (!empty($this->BuildInformation->PullRequest)) {
                    $build->SetPullRequest($value);
                }
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildInformation->BuildName;
                $build->SetStamp($this->BuildInformation->BuildStamp);
                $build->Generator = $this->BuildInformation->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[''] = $build;
            }
        } elseif ($name == 'WARNING') {
            $this->Error = new BuildError();
            $this->Error->Type = 1;
        } elseif ($name == 'ERROR') {
            $this->Error = new BuildError();
            $this->Error->Type = 0;
        } elseif ($name == 'FAILURE') {
            $this->Error = new BuildFailure();
            $this->Error->Type = 0;
            if ($attributes['TYPE'] == 'Error') {
                $this->Error->Type = 0;
            } elseif ($attributes['TYPE'] == 'Warning') {
                $this->Error->Type = 1;
            }
        } elseif ($name == 'LABEL') {
            $this->Label = new Label();
        }
    }

    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);

        if ($name == 'BUILD') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            $submit_time = gmdate(FMT_DATETIME);
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
                $build->UpdateBuildDuration(
                        $this->EndTimeStamp - $this->StartTimeStamp);
                $build->ComputeDifferences();

                global $CDASH_ENABLE_FEED;
                if ($CDASH_ENABLE_FEED) {
                    // Insert the build into the feed
                    $this->Feed->InsertBuild($this->projectid, $build->Id);
                }
            }
        } elseif ($name == 'WARNING' || $name == 'ERROR' || $name == 'FAILURE') {
            global $CDASH_LARGE_TEXT_LIMIT;
            $threshold = $CDASH_LARGE_TEXT_LIMIT;

            if ($threshold > 0 && isset($this->Error->StdOutput)) {
                $outlen = strlen($this->Error->StdOutput);
                if ($outlen > $threshold) {
                    $tmp = substr($this->Error->StdOutput, 0, $threshold);
                    unset($this->Error->StdOutput);
                    $this->Error->StdOutput = $tmp .
                        "\n...\nCDash truncated output because it exceeded $threshold characters.\n";
                }

                $errlen = strlen($this->Error->StdError);
                if ($errlen > $threshold) {
                    $tmp = substr($this->Error->StdError, 0, $threshold);
                    unset($this->Error->StdError);
                    $this->Error->StdError = $tmp .
                        "\n...\nCDash truncated output because it exceeded $threshold characters.\n";
                }
            }
            if (array_key_exists($this->SubProjectName, $this->Builds)) {
                add_log("HANDLER ADD ERROR to ".$this->SubProjectName, LOG_ERR);
                $this->Builds[$this->SubProjectName]->AddError($this->Error);
            }
            unset($this->Error);
        } elseif ($name == 'LABEL') {
            if (isset($this->Error)) {
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
                    $this->BuildCommand = $data;
                    break;
                case 'LOG':
                    $this->BuildLog .= $data;
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
            global $CDASH_LARGE_TEXT_LIMIT;
            $threshold = $CDASH_LARGE_TEXT_LIMIT;
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
        } elseif ($element == 'LABEL') {
            $this->Label->SetText($data);
        }
    }

    public function getBuildStamp()
    {
        return $this->BuildInformation->BuildStamp;
    }

    public function getBuildName()
    {
        return $this->BuildInformation->BuildName;
    }
}
