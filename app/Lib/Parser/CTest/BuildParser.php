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

namespace CDash\Lib\Parser\CTest;

use CDash\Lib\Parser\AbstractXmlParser;
use CDash\Model\Build;
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;
use CDash\Model\BuildInformation;
use CDash\Model\Feed;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

/**
 * Class BuildParser
 * @package CDash\Lib\Parser\CTest
 */
class BuildParser extends AbstractXmlParser
{
    private $error;
    private $label;
    private $append;
    private $feed;
    private $builds;
    private $buildInformation;
    private $buildCommand;
    private $buildLog;
    private $labels;
    // Map SubProjects to Labels
    private $subProjects;
    private $errorSubProjectName;
    private $buildName;
    private $buildStamp;
    private $generator;
    private $pullRequest;

    /**
     * BuildParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->builds = [];
        $this->site = $this->getInstance(Site::class);
        $this->append = false;
        $this->feed = $this->getInstance(Feed::class);
        $this->buildLog = '';
        $this->labels = [];
        $this->subProjects = [];
    }

    /**
     * @param $parser
     * @param $name
     * @param $attributes
     * @return mixed|void
     */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);

        if ($name == 'SITE') {
            $this->site->Name = $attributes['NAME'];
            if (empty($this->site->Name)) {
                $this->site->Name = '(empty)';
            }
            $this->site->Insert();

            $siteInformation = $this->getInstance(SiteInformation::class);
            $this->buildInformation = $this->getInstance(BuildInformation::class);
            $this->buildName = "";
            $this->buildStamp = "";
            $this->generator = "";
            $this->pullRequest = "";

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                if ($key === 'BUILDNAME') {
                    $this->buildName = $value;
                } elseif ($key === 'BUILDSTAMP') {
                    $this->buildStamp = $value;
                } elseif ($key === 'GENERATOR') {
                    $this->generator = $value;
                } elseif ($key == 'CHANGEID') {
                    $this->pullRequest = $value;
                } else {
                    $siteInformation->SetValue($key, $value);
                    $this->buildInformation->SetValue($key, $value);
                }
            }

            if (empty($this->buildName)) {
                $this->buildName = '(empty)';
            }

            $this->site->SetInformation($siteInformation);

            if (array_key_exists('APPEND', $attributes)) {
                if (strtolower($attributes['APPEND']) == 'true') {
                    $this->append = true;
                }
            } else {
                $this->append = false;
            }
        } elseif ($name == 'SUBPROJECT') {
            $this->subProjectName = $attributes['NAME'];
            if (!array_key_exists($this->subProjectName, $this->subProjects)) {
                $this->subProjects[$this->subProjectName] = array();
            }
            if (!array_key_exists($this->subProjectName, $this->builds)) {
                $build = $this->getInstance(Build::class);
                if (!empty($this->pullRequest)) {
                    $build->SetPullRequest($this->pullRequest);
                }
                $build->SiteId = $this->site->Id;
                $build->Name = $this->buildName;
                $build->SetStamp($this->buildStamp);
                $build->Generator = $this->generator;
                $build->Information = $this->buildInformation;
                $this->builds[$this->subProjectName] = $build;
            }
        } elseif ($name == 'BUILD') {
            if (empty($this->builds)) {
                // No subprojects
                $build = $this->getInstance(Build::class);
                if (!empty($this->pullRequest)) {
                    $build->SetPullRequest($this->pullRequest);
                }
                $build->SiteId = $this->site->Id;
                $build->Name = $this->buildName;
                $build->SetStamp($this->buildStamp);
                $build->Generator = $this->generator;
                $build->Information = $this->buildInformation;
                $this->builds[''] = $build;
            }
        } elseif ($name == 'WARNING') {
            $this->error = $this->getInstance(BuildError::class);
            $this->error->Type = 1;
            $this->errorSubProjectName = "";
        } elseif ($name == 'ERROR') {
            $this->error = $this->getInstance(BuildError::class);
            $this->error->Type = 0;
            $this->errorSubProjectName = "";
        } elseif ($name == 'FAILURE') {
            $this->error = $this->getInstance(BuildFailure::class);
            $this->error->Type = 0;
            if ($attributes['TYPE'] == 'Error') {
                $this->error->Type = 0;
            } elseif ($attributes['TYPE'] == 'Warning') {
                $this->error->Type = 1;
            }
            $this->errorSubProjectName = "";
        } elseif ($name == 'LABEL') {
            $this->label = $this->getInstance(Label::class);
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed|void
     */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($name == 'BUILD') {
            $start_time = gmdate(FMT_DATETIME, $this->startTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->endTimeStamp);
            $submit_time = gmdate(FMT_DATETIME);
            // Do not add each build's duration to the parent's tally if this
            // XML file represents multiple "all-at-once" SubProject builds.
            $all_at_once = count($this->builds) > 1;
            $parent_duration_set = false;
            foreach ($this->builds as $subproject => $build) {
                $build->ProjectId = $this->projectId;
                $build->StartTime = $start_time;
                $build->EndTime = $end_time;
                $build->SubmitTime = $submit_time;
                if (empty($subproject)) {
                    $build->SetSubProject($this->subProjectName);
                } else {
                    $build->SetSubProject($subproject);
                }
                $build->Append = $this->append;
                $build->Command = $this->buildCommand;
                $build->Log .= $this->buildLog;

                foreach ($this->labels as $label) {
                    $build->AddLabel($label);
                }
                add_build($build, $this->scheduleId);

                $duration = $this->endTimeStamp - $this->startTimeStamp;
                $build->UpdateBuildDuration($duration, !$all_at_once);
                if ($all_at_once && !$parent_duration_set) {
                    $parent_build = new Build();
                    $parent_build->Id = $build->GetParentId();
                    $parent_build->UpdateBuildDuration($duration, false);
                    $parent_duration_set = true;
                }

                $build->ComputeDifferences();

                if ($this->getConfigValue('CDASH_ENABLE_FEED')) {
                    // Insert the build into the feed
                    $this->feed->InsertBuild($this->projectId, $build->Id);
                }
            }
        } elseif ($name == 'WARNING' || $name == 'ERROR' || $name == 'FAILURE') {
            $threshold = $this->getConfigValue('CDASH_LARGE_TEXT_LIMIT');

            if ($threshold > 0 && isset($this->error->StdOutput)) {
                $chunk_size = $threshold / 2;
                $outlen = strlen($this->error->StdOutput);
                if ($outlen > $threshold) {
                    $beginning = substr($this->error->StdOutput, 0, $chunk_size);
                    $end = substr($this->error->StdOutput, -$chunk_size);
                    unset($this->error->StdOutput);
                    $this->error->StdOutput =
                        "$beginning\n...\nCDash truncated output because it exceeded $threshold characters.\n...\n$end\n";
                    $outlen = strlen($this->error->StdOutput);
                }

                $errlen = strlen($this->error->StdError);
                if ($errlen > $threshold) {
                    $beginning = substr($this->error->StdError, 0, $chunk_size);
                    $end = substr($this->error->StdError, -$chunk_size);
                    unset($this->error->StdError);
                    $this->error->StdError =
                        "$beginning\n...\nCDash truncated output because it exceeded $threshold characters.\n...\n$end\n";
                    $errlen = strlen($this->error->StdError);
                }
            }
            if (array_key_exists($this->subProjectName, $this->builds)) {
                $this->builds[$this->subProjectName]->AddError($this->error);
            }
            unset($this->error);
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            if (!empty($this->errorSubProjectName)) {
                $this->subProjectName = $this->errorSubProjectName;
            } elseif (isset($this->error)) {
                $this->error->AddLabel($this->label);
            } else {
                $this->labels[] = $this->label;
            }
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent == 'BUILD') {
            switch ($element) {
                case 'STARTBUILDTIME':
                    $this->startTimeStamp = $data;
                    break;
                case 'ENDBUILDTIME':
                    $this->endTimeStamp = $data;
                    break;
                case 'BUILDCOMMAND':
                    $this->buildCommand = htmlspecialchars_decode($data);
                    break;
                case 'LOG':
                    $this->buildLog .= htmlspecialchars_decode($data);
                    break;
            }
        } elseif ($parent == 'ACTION') {
            switch ($element) {
                case 'LANGUAGE':
                    $this->error->Language .= $data;
                    break;
                case 'SOURCEFILE':
                    $this->error->SourceFile .= $data;
                    break;
                case 'TARGETNAME':
                    $this->error->TargetName .= $data;
                    break;
                case 'OUTPUTFILE':
                    $this->error->OutputFile .= $data;
                    break;
                case 'OUTPUTTYPE':
                    $this->error->OutputType .= $data;
                    break;
            }
        } elseif ($parent == 'COMMAND') {
            switch ($element) {
                case 'WORKINGDIRECTORY':
                    $this->error->WorkingDirectory .= $data;
                    break;
                case 'ARGUMENT':
                    $this->error->AddArgument($data);
                    break;
            }
        } elseif ($parent == 'RESULT') {
            $threshold = $this->getConfigValue('CDASH_LARGE_TEXT_LIMIT');
            $append = true;

            switch ($element) {
                case 'STDOUT':
                    if ($threshold > 0) {
                        if (strlen($this->error->StdOutput) > $threshold) {
                            $append = false;
                        }
                    }

                    if ($append) {
                        $this->error->StdOutput .= $data;
                    }
                    break;

                case 'STDERR':
                    if ($threshold > 0) {
                        if (strlen($this->error->StdError) > $threshold) {
                            $append = false;
                        }
                    }

                    if ($append) {
                        $this->error->StdError .= $data;
                    }
                    break;

                case 'EXITCONDITION':
                    $this->error->ExitCondition .= $data;
                    break;
            }
        } elseif ($element == 'BUILDLOGLINE') {
            $this->error->LogLine .= $data;
        } elseif ($element == 'TEXT') {
            $this->error->Text .= $data;
        } elseif ($element == 'SOURCEFILE') {
            $this->error->SourceFile .= $data;
        } elseif ($element == 'SOURCELINENUMBER') {
            $this->error->SourceLine .= $data;
        } elseif ($element == 'PRECONTEXT') {
            $this->error->PreContext .= $data;
        } elseif ($element == 'POSTCONTEXT') {
            $this->error->PostContext .= $data;
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->subProjects[$this->subProjectName][] =  $data;
        } elseif ($parent == 'LABELS' && $element == 'LABEL') {
            // First, check if this label belongs to a SubProject
            foreach ($this->subProjects as $subproject => $labels) {
                if (in_array($data, $labels)) {
                    $this->errorSubProjectName = $subproject;
                    break;
                }
            }
            if (empty($this->errorSubProjectName)) {
                $this->label->SetText($data);
            }
        }
    }

    /**
     * @return string
     */
    public function getBuildStamp()
    {
        return $this->buildStamp;
    }

    /**
     * @return string
     */
    public function getBuildName()
    {
        return $this->buildName;
    }

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->builds);
    }

    /**
     * @return array|\CDash\Lib\Collection\BuildCollection
     */
    public function getActionableBuilds()
    {
        return $this->builds;
    }
}
