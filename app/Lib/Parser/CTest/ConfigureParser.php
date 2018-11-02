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
use CDash\Model\BuildConfigure;
use CDash\Model\BuildInformation;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

/**
 * Class ConfigureParser
 * @package CDash\Lib\Parser\CTest
 */
class ConfigureParser extends AbstractXmlParser
{
    private $builds;
    private $buildInformation;
    private $subProjects;
    private $configure;
    private $label;
    private $notified;
    private $buildName;
    private $buildStamp;
    private $generator;
    private $pullRequest;

    /**
     * ConfigureParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->builds = [];
        $this->subProjects = [];
        $this->site = $this->getInstance(Site::class);
        $this->configure = $this->getInstance(BuildConfigure::class);
        $this->startTimeStamp = 0;
        $this->endTimeStamp = 0;
        // Only complain about errors & warnings once.
        $this->notified = false;
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
        } elseif ($name == 'SUBPROJECT') {
            $this->subProjectName = $attributes['NAME'];
            if (!array_key_exists($this->subProjectName, $this->subProjects)) {
                $this->subProjects[$this->subProjectName] = array();
            }
            if (!array_key_exists($this->subProjectName, $this->builds)) {
                $build = $this->getInstance(Build::class);
                $build->SiteId = $this->site->Id;
                $build->Name = $this->buildName;
                $build->SetStamp($this->buildStamp);
                $build->Generator = $this->generator;
                $build->Information = $this->buildInformation;
                $this->builds[$this->subProjectName] = $build;
            }
        } elseif ($name == 'CONFIGURE') {
            if (empty($this->builds)) {
                // No subprojects
                $build = $this->getInstance(Build::class);
                $build->SiteId = $this->site->Id;
                $build->Name = $this->buildName;
                $build->SetStamp($this->buildStamp);
                $build->Generator = $this->generator;
                $build->Information = $this->buildInformation;
                $this->builds[''] = $build;
            }
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
        $parent = $this->getParent();
        parent::endElement($parser, $name);

        if ($name == 'CONFIGURE') {
            $start_time = gmdate(FMT_DATETIME, $this->startTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->endTimeStamp);
            // Configure Duration is handled differently if this XML
            // file represents multiple builds.  We refer to this situation
            // as an "all at once" SubProject build.  In this case, we do
            // not want to add each build's configure duration to the parent's
            // tally.
            $all_at_once = count($this->builds) > 1;
            $parent_duration_set = false;

            foreach ($this->builds as $subproject => $build) {
                $build->ProjectId = $this->projectId;
                $build->StartTime = $start_time;
                $build->EndTime = $end_time;
                $build->SubmitTime = gmdate(FMT_DATETIME);
                if (empty($subproject)) {
                    $build->SetSubProject($this->subProjectName);
                    $build->GetIdFromName($this->subProjectName);
                } else {
                    $build->SetSubProject($subproject);
                    $build->GetIdFromName($subproject);
                }
                $build->InsertErrors = false;
                if (!$this->notified && !empty($this->pullRequest)) {
                    // Only perform PR notification for the first build parsed.
                    $build->SetPullRequest($this->pullRequest);
                    $this->notified = true;
                }

                $build->RemoveIfDone();
                if ($build->Id == 0) {
                    // If the build doesn't exist we add it
                    add_build($build, $this->scheduleId);
                } else {
                    // Otherwise we make sure that it's up-to-date.
                    $build->UpdateBuild($build->Id, -1, -1);
                }
                $GLOBALS['PHP_ERROR_BUILD_ID'] = $build->Id;
                $this->configure->BuildId = $build->Id;
                $this->configure->StartTime = $start_time;
                $this->configure->EndTime = $end_time;

                // Insert the configure
                if ($this->configure->Insert()) {
                    // Insert errors from the log file
                    $this->configure->ComputeWarnings();
                    $this->configure->ComputeErrors();
                }

                // Record the number of warnings & errors with the build.
                $build->SetNumberOfConfigureWarnings(
                    $this->configure->NumberOfWarnings);
                $build->SetNumberOfConfigureErrors(
                    $this->configure->NumberOfErrors);

                $build->ComputeConfigureDifferences();

                // Record configure duration with the build.
                $duration = $this->endTimeStamp - $this->startTimeStamp;
                $build->SetConfigureDuration($duration, !$all_at_once);
                if ($all_at_once && !$parent_duration_set) {
                    $parent_build = $this->getInstance(Build::class);
                    $parent_build->Id = $build->GetParentId();
                    $parent_build->SetConfigureDuration($duration, false);
                    $parent_duration_set = true;
                }
            }

            // Update the tally of warnings & errors in the parent build,
            // if applicable.
            // All subprojects share the same configure file and parent build,
            // so only need to do this once
            $build->UpdateParentConfigureNumbers(
                $this->configure->NumberOfWarnings, $this->configure->NumberOfErrors);
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            if (isset($this->configure)) {
                $this->configure->AddLabel($this->label);
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

        if ($parent == 'CONFIGURE') {
            switch ($element) {
                case 'STARTCONFIGURETIME':
                    $this->startTimeStamp = $data;
                    break;
                case 'ENDCONFIGURETIME':
                    $this->endTimeStamp = $data;
                    break;
                case 'BUILDCOMMAND':
                    $this->configure->Command .= $data;
                    break;
                case 'LOG':
                    $this->configure->Log .= $data;
                    break;
                case 'CONFIGURECOMMAND':
                    $this->configure->Command .= $data;
                    break;
                case 'CONFIGURESTATUS':
                    $this->configure->Status .= $data;
                    break;
            }
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->subProjects[$this->subProjectName][] =  $data;
        } elseif ($parent == 'LABELS' && $element == 'LABEL') {
            // First, check if this label belongs to a SubProject
            $subproject_name = "";
            foreach ($this->subProjects as $subproject => $labels) {
                if (in_array($data, $labels)) {
                    $subproject_name = $subproject;
                    break;
                }
            }
            if (empty($subproject_name)) {
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
}
