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
require_once 'include/Submission/CommitAuthorHandlerTrait.php';
require_once 'include/Submission/CommitAuthorHandlerInterface.php';

use CDash\Collection\BuildCollection;
use CDash\Collection\Collection;
use CDash\Collection\SubscriptionBuilderCollection;
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\BuildError;
use CDash\Model\BuildErrorFilter;
use CDash\Model\BuildFailure;
use CDash\Model\BuildGroup;
use App\Models\BuildInformation;
use CDash\Model\Label;
use CDash\Model\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use CDash\Model\SubscriberInterface;
use CDash\Submission\CommitAuthorHandlerInterface;
use CDash\Submission\CommitAuthorHandlerTrait;

class BuildHandler extends AbstractHandler implements ActionableBuildInterface, CommitAuthorHandlerInterface
{
    use CommitAuthorHandlerTrait;

    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Error;
    private $Label;
    private $Builds;
    private BuildInformation $BuildInformation;
    private $BuildCommand;
    private $BuildGroup;
    private $BuildLog;
    private $Labels;
    // Map SubProjects to Labels
    private $SubProjects;
    private $ErrorSubProjectName;
    private $BuildName;
    private $BuildStamp;
    private $Generator;
    private $PullRequest;
    private $BuildErrorFilter;

    public function __construct($projectid)
    {
        parent::__construct($projectid);
        $this->Builds = [];
        $this->BuildCommand = '';
        $this->BuildLog = '';
        $this->Labels = [];
        $this->SubProjects = [];
        $project = new Project();
        $project->Id = $projectid;
        $this->BuildErrorFilter = new BuildErrorFilter($project);
    }

    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $factory = $this->getModelFactory();

        if ($name == 'SITE') {
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation();
            $this->BuildInformation = new BuildInformation();
            $this->BuildName = "";
            $this->BuildStamp = "";
            $this->SubProjectName = "";
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
                } elseif ($key === 'CHANGEID') {
                    $this->PullRequest = $value;
                } else {
                    $siteInformation->SetValue($key, $value);
                    $this->BuildInformation->SetValue($key, $value);
                }
            }

            if (empty($this->BuildName)) {
                $this->BuildName = '(empty)';
            }

            $this->Site->mostRecentInformation()->save($siteInformation);
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = [];
            }
            if (!array_key_exists($this->SubProjectName, $this->Builds)) {
                $build = $factory->create(Build::class);
                if (!empty($this->PullRequest)) {
                    $build->SetPullRequest($this->PullRequest);
                }
                $build->SiteId = $this->Site->id;
                $build->Name = $this->BuildName;
                $build->SetStamp($this->BuildStamp);
                $build->Generator = $this->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[$this->SubProjectName] = $build;
            }
        } elseif ($name == 'BUILD') {
            if (empty($this->Builds)) {
                // No subprojects
                $build = $factory->create(Build::class);
                if (!empty($this->PullRequest)) {
                    $build->SetPullRequest($this->PullRequest);
                }
                $build->SiteId = $this->Site->id;
                $build->Name = $this->BuildName;
                $build->SetStamp($this->BuildStamp);
                $build->Generator = $this->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[''] = $build;
            }
        } elseif ($name == 'WARNING') {
            $this->Error = $factory->create(BuildError::class);
            $this->Error->Type = 1;
            $this->ErrorSubProjectName = "";
        } elseif ($name == 'ERROR') {
            $this->Error = $factory->create(BuildError::class);
            $this->Error->Type = 0;
            $this->ErrorSubProjectName = "";
        } elseif ($name == 'FAILURE') {
            $this->Error = $factory->create(BuildFailure::class);
            $this->Error->Type = 0;
            if ($attributes['TYPE'] == 'Error') {
                $this->Error->Type = 0;
            } elseif ($attributes['TYPE'] == 'Warning') {
                $this->Error->Type = 1;
            }
            $this->ErrorSubProjectName = "";
        } elseif ($name == 'LABEL') {
            $this->Label = $factory->create(Label::class);
        }
    }

    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);
        $factory = $this->getModelFactory();

        if ($name == 'BUILD') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            $submit_time = gmdate(FMT_DATETIME);
            // Do not add each build's duration to the parent's tally if this
            // XML file represents multiple "all-at-once" SubProject builds.
            $all_at_once = count($this->Builds) > 1;
            $parent_duration_set = false;
            /**
             * @var Build $build
             */
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
                add_build($build);

                $duration = $this->EndTimeStamp - $this->StartTimeStamp;
                $build->UpdateBuildDuration((int) $duration, !$all_at_once);
                if ($all_at_once && !$parent_duration_set) {
                    $parent_build = new Build();
                    $parent_build->Id = $build->GetParentId();
                    $parent_build->UpdateBuildDuration((int) $duration, false);
                    $parent_duration_set = true;
                }

                $build->ComputeDifferences();
            }
        } elseif ($name == 'WARNING' || $name == 'ERROR' || $name == 'FAILURE') {
            $skip_error = false;
            foreach (['StdOutput', 'StdError', 'Text'] as $field) {
                if (isset($this->Error->$field)) {
                    if ($this->Error->Type === 1) {
                        $skip_error = $this->BuildErrorFilter->FilterWarning($this->Error->$field);
                    } elseif ($this->Error->Type === 0) {
                        $skip_error = $this->BuildErrorFilter->FilterError($this->Error->$field);
                    }
                }
            }
            if ($skip_error) {
                unset($this->Error);
                return;
            }

            $threshold = config('cdash.large_text_limit');
            if ($threshold > 0) {
                $chunk_size = $threshold / 2;
                foreach (['StdOutput', 'StdError'] as $field) {
                    if (isset($this->Error->$field)) {
                        $outlen = strlen($this->Error->$field);
                        if ($outlen > $threshold) {
                            // First try removing suppressed warnings to see
                            // if that gets us under the threshold.
                            $this->Error->$field = $this->removeSuppressedWarnings($this->Error->$field);
                        }
                        $outlen = strlen($this->Error->$field);
                        if ($outlen > $threshold) {
                            // Truncate the middle of the output if it is
                            // still too long.
                            $beginning = substr($this->Error->$field, 0, $chunk_size);
                            $end = substr($this->Error->$field, -$chunk_size);
                            unset($this->Error->$field);
                            $this->Error->$field =
                                "$beginning\n...\nCDash truncated output because it exceeded $threshold characters.\n...\n$end\n";
                        }
                    }
                }
            }

            if (array_key_exists($this->SubProjectName, $this->Builds)) {
                // TODO: temporary fix for subtle, hard to track down issue
                // BuildFailures' labels are not getting set in label2buildfailure when using new
                // subproject xml schema, e.g. (<SubProject name="..."><Label>...</Label></SubProject>.
                // If the error is being set because this is a SubProject then presumably we may
                // ensure that the label exists here, and if not, create it.

                if (isset($this->Error->Labels)) {
                    $hasLabel = false;
                    foreach ($this->Labels as $lbl) {
                        if ($lbl->Text === $this->SubProjectName) {
                            $hasLabel = true;
                            break;
                        }
                    }
                    if (!$hasLabel) {
                        $label = $factory->create(Label::class);
                        $label->SetText($this->SubProjectName);
                        $this->Error->AddLabel($label);
                    }
                }

                $this->Builds[$this->SubProjectName]->AddError($this->Error);
            }
            unset($this->Error);
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            if (!empty($this->ErrorSubProjectName)) {
                $this->SubProjectName = $this->ErrorSubProjectName;
            } elseif (isset($this->Error) && $this->Error instanceof BuildFailure) {
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
            switch ($element) {
                case 'STDOUT':
                    $this->Error->StdOutput .= $data;
                    break;

                case 'STDERR':
                    $this->Error->StdError .= $data;
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

    public function getSubProjectName()
    {
        return $this->SubProjectName;
    }

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->Builds);
    }

    /**
     * @return array|Build[]
     */
    public function getActionableBuilds()
    {
        return $this->Builds;
    }

    /**
     * @return BuildCollection
     * TODO: consider refactoring into abstract_handler asap
     */
    public function GetBuildCollection()
    {
        $factory = $this->getModelFactory();
        /** @var BuildCollection $collection */
        $collection = $factory->create(BuildCollection::class);
        foreach ($this->Builds as $key => $build) {
            if (is_numeric($key)) {
                $collection->add($build);
            } else {
                $collection->addItem($build, $key);
            }
        }
        return $collection;
    }

    /**
     * @param SubscriberInterface $subscriber
     * @return TopicCollection
     */
    public function GetTopicCollectionForSubscriber(SubscriberInterface $subscriber)
    {
        $collection = new TopicCollection();
        $errors = new BuildErrorTopic();
        $errors->setType(Build::TYPE_ERROR);
        if ($errors->isSubscribedToBy($subscriber)) {
            $collection->add($errors);
        }

        $warnings = new BuildErrorTopic();
        $warnings->setType(Build::TYPE_WARN);
        if ($warnings->isSubscribedToBy($subscriber)) {
            $collection->add($warnings);
        }
        return $collection;
    }

    /**
     * @return Collection
     */
    public function GetSubscriptionBuilderCollection()
    {
        $collection = (new SubscriptionBuilderCollection)
            ->add(new UserSubscriptionBuilder($this))
            ->add(new CommitAuthorSubscriptionBuilder($this));
        return $collection;
    }

    public function GetBuildGroup()
    {
        $factory = $this->getModelFactory();
        $buildGroup = $factory->create(BuildGroup::class);
        foreach ($this->Builds as $build) {
            $buildGroup->SetId($build->GroupId);
            break;
        }
        return $buildGroup;
    }

    private function removeSuppressedWarnings($input)
    {
        if (strpos($input, '[CTest: warning suppressed]') === false) {
            return $input;
        }
        // Iterate over the input string line-by-line,
        // keeping any content following "warning matched" but removing
        // any content following "warning suppressed".
        $output = '';
        $separator = "\r\n";
        $line = strtok($input, $separator);
        $preserve = true;
        while ($line !== false) {
            if (strpos($line, '[CTest: warning suppressed') !== false) {
                $preserve = false;
            }
            if (strpos($line, '[CTest: warning matched') !== false) {
                $preserve = true;
            }
            if ($preserve) {
                $output .= "{$line}\n";
            }
            $line = strtok($separator);
        }
        return $output;
    }
}
