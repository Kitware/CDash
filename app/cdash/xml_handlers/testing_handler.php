<?php

require_once 'xml_handlers/abstract_handler.php';
require_once 'xml_handlers/actionable_build_interface.php';

use App\Models\TestMeasurement;
use App\Services\TestCreator;

use CDash\Collection\BuildCollection;
use CDash\Collection\Collection;
use CDash\Collection\SubscriptionBuilderCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\MissingTestTopic;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\BuildInformation;
use CDash\Model\Image;
use CDash\Model\Label;
use CDash\Model\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use CDash\Model\SubscriberInterface;
use CDash\Submission\CommitAuthorHandlerInterface;
use CDash\Submission\CommitAuthorHandlerTrait;

class TestingHandler extends AbstractHandler implements ActionableBuildInterface, CommitAuthorHandlerInterface
{
    use CommitAuthorHandlerTrait;

    private $StartTimeStamp;
    private $EndTimeStamp;

    private $TestMeasurement;
    private $Label;

    private $TestCreator;

    /** @var Build[] Builds */
    private $Builds;
    private $BuildInformation;

    // Map SubProjects to Labels
    private $SubProjects;
    private $TestSubProjectName;
    private $BuildName;
    private $BuildStamp;
    private $Generator;
    private $PullRequest;

    // Keep a record of the number of tests passed, failed and notrun
    // This works only because we have one test file per submission
    private $NumberTestsFailed;
    private $NumberTestsNotRun;
    private $NumberTestsPassed;

    /** Constructor */
    public function __construct($projectID)
    {
        parent::__construct($projectID);
        $this->Builds = [];
        $this->SubProjects = [];
        $this->NumberTestsFailed = [];
        $this->NumberTestsNotRun = [];
        $this->NumberTestsPassed = [];
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
    }

    /** Start Element */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $parent = $this->getParent(); // should be before endElement
        $factory = $this->getModelFactory();

        if ($name == 'SITE') {
            $this->Project = $factory->create(Project::class);
            $this->Project->Id = $this->projectid;

            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation;
            $this->BuildInformation = $factory->create(BuildInformation::class);
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
            $this->Site->mostRecentInformation()->save($siteInformation);
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = [];
                $this->createBuild();
            }
        } elseif ($name == 'TEST' && count($attributes) > 0) {
            $this->TestCreator = new TestCreator;
            $this->TestCreator->projectid = $this->projectid;
            $this->TestCreator->testStatus = $attributes['STATUS'];
            $this->TestSubProjectName = '';
            $this->Labels = [];
        } elseif ($name == 'NAMEDMEASUREMENT' && array_key_exists('TYPE', $attributes)) {
            $this->TestMeasurement = $factory->create(TestMeasurement::class);

            if ($attributes['TYPE'] == 'file') {
                $this->TestMeasurement->name = $attributes['FILENAME'];
            } else {
                $this->TestMeasurement->name = $attributes['NAME'];
            }
            $this->TestMeasurement->type = $attributes['TYPE'];
        } elseif ($name == 'VALUE' && $parent == 'MEASUREMENT') {
            if (isset($attributes['COMPRESSION']) && $attributes['COMPRESSION'] == 'gzip') {
                $this->TestCreator->alreadyCompressed = true;
            }
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            $this->Label = $factory->create(Label::class);
        }
    }

    /** End Element */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);
        $factory = $this->getModelFactory();

        if ($name == 'TEST' && $parent == 'TESTING') {
            // By now, will either have one subproject for the entire file
            // Or a subproject specifically for this test
            // Or no subprojects.
            if (!array_key_exists($this->SubProjectName, $this->Builds)) {
                $this->createBuild();
            }

            $build = $this->Builds[$this->SubProjectName];

            $GLOBALS['PHP_ERROR_BUILD_ID'] = $build->Id;

            if ($this->TestCreator->testStatus == 'passed') {
                $this->NumberTestsPassed[$this->SubProjectName]++;
            } elseif ($this->TestCreator->testStatus == 'failed') {
                $this->NumberTestsFailed[$this->SubProjectName]++;
            } elseif ($this->TestCreator->testStatus == 'notrun') {
                $this->NumberTestsNotRun[$this->SubProjectName]++;
            }
            if ($this->Labels) {
                $this->TestCreator->labels = $this->Labels;
            }
            $this->TestCreator->create($build);
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            if (!empty($this->TestSubProjectName)) {
                $this->SubProjectName = $this->TestSubProjectName;
            }
        } elseif ($name == 'NAMEDMEASUREMENT') {
            if ($this->TestMeasurement->name == 'Execution Time') {
                $this->TestCreator->buildTestTime = $this->TestMeasurement->value;
            } elseif ($this->TestMeasurement->name == 'Exit Code') {
                if (strlen($this->TestCreator->testDetails) > 0 && $this->TestMeasurement->value) {
                    $this->TestCreator->testDetails .= ' (' . $this->TestMeasurement->value . ')';
                } elseif ($this->TestMeasurement->value) {
                    $this->TestCreator->testDetails = $this->TestMeasurement->value;
                }
            } elseif ($this->TestMeasurement->name == 'Completion Status') {
                if (strlen($this->TestCreator->testDetails) > 0) {
                    $this->TestCreator->testDetails = $this->TestMeasurement->value . ' (' . $this->TestCreator->testDetails . ')';
                } else {
                    $this->TestCreator->testDetails = $this->TestMeasurement->value;
                }
            } elseif ($this->TestMeasurement->name == 'Command Line') {
                // don't do anything since it should already be in the FullCommandLine
            } else {
                // explicit measurement

                // If it's an image we add it as an image
                if (strpos($this->TestMeasurement->type, 'image') !== false) {
                    $image = $factory->create(Image::class);
                    $image->Extension = $this->TestMeasurement->type;
                    $image->Data = $this->TestMeasurement->value;
                    $image->Name = $this->TestMeasurement->name;
                    $this->TestCreator->images->push($image);
                } else {
                    $this->TestMeasurement->value = trim($this->TestMeasurement->value ?? '');
                    $this->TestCreator->measurements->push($this->TestMeasurement);
                }
            }
        } elseif ($name == 'SITE') {
            // If we've gotten this far without creating any builds, there's no
            // tests. Create a build anyway.
            if (empty($this->Builds)) {
                $this->createBuild();
            }

            // Do not accumulate the parent's testing duration if this
            // XML file represents multiple "all-at-once" SubProject builds.
            $all_at_once = count($this->Builds) > 1;
            $parent_duration_set = false;
            foreach ($this->Builds as $subproject => $build) {
                $build->StartTime = gmdate(FMT_DATETIME, $this->StartTimeStamp);
                $build->EndTime = gmdate(FMT_DATETIME, $this->EndTimeStamp);
                $build->UpdateBuild($build->Id, -1, -1);

                // Update the number of tests in the Build table
                $build->UpdateTestNumbers($this->NumberTestsPassed[$subproject],
                    $this->NumberTestsFailed[$subproject],
                    $this->NumberTestsNotRun[$subproject]);

                // Is it really necessary to have to load the build from the db here?
                $build->ComputeTestTiming();

                if ($this->StartTimeStamp > 0 && $this->EndTimeStamp > 0) {
                    // Update test duration in the Build table.
                    $duration = $this->EndTimeStamp - $this->StartTimeStamp;
                    $build->UpdateTestDuration($duration, !$all_at_once);
                    if ($all_at_once && !$parent_duration_set) {
                        $parent_build = $factory->create(Build::class);
                        $parent_build->Id = $build->GetParentId();
                        $parent_build->UpdateTestDuration($duration, false);
                        $parent_duration_set = true;
                    }
                }
                $build->SaveTotalTestsTime();
            }
        }
    }

    /** Text function */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'TESTING') {
            switch ($element) {
                case 'STARTTESTTIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'ENDTESTTIME':
                    $this->EndTimeStamp = $data;
                    break;
            }
        } elseif ($parent == 'TEST') {
            switch ($element) {
                case 'NAME':
                    $this->TestCreator->setTestName($data);
                    break;
                case 'PATH':
                    $this->TestCreator->testPath = $data;
                    break;
                case 'FULLCOMMANDLINE':
                    $this->TestCreator->testCommand .= $data;
                    break;
            }
        } elseif ($parent == 'NAMEDMEASUREMENT' && $element == 'VALUE') {
            if (!isset($this->TestMeasurement->value)) {
                $this->TestMeasurement->value = $data;
            } else {
                $this->TestMeasurement->value .= $data;
            }
        } elseif ($parent == 'MEASUREMENT' && $element == 'VALUE') {
            $this->TestCreator->testOutput .= $data;
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->SubProjects[$this->SubProjectName][] =  $data;
        } elseif ($parent == 'LABELS' && $element == 'LABEL') {
            // Check if this label belongs to a SubProject.
            foreach ($this->SubProjects as $subproject => $labels) {
                if (in_array($data, $labels)) {
                    $this->TestSubProjectName = $subproject;
                    break;
                }
            }
            if (is_a($this->Label, Label::class)) {
                $this->Label->SetText($data);
                $this->Labels[] = $this->Label;
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

    private function createBuild()
    {
        if (!array_key_exists($this->SubProjectName, $this->NumberTestsFailed)) {
            $this->NumberTestsFailed[$this->SubProjectName] = 0;
        }
        if (!array_key_exists($this->SubProjectName, $this->NumberTestsNotRun)) {
            $this->NumberTestsNotRun[$this->SubProjectName] = 0;
        }
        if (!array_key_exists($this->SubProjectName, $this->NumberTestsPassed)) {
            $this->NumberTestsPassed[$this->SubProjectName] = 0;
        }
        $factory = $this->getModelFactory();
        $build = $factory->create(Build::class);
        $build->SetSite($this->Site);

        if (!empty($this->PullRequest)) {
            $build->SetPullRequest($this->PullRequest);
        }

        $build->SiteId = $this->Site->id;
        $build->Name = $this->BuildName;
        $build->SubProjectName = $this->SubProjectName;
        $build->SetStamp($this->BuildStamp);
        $build->Generator = $this->Generator;
        $build->Information = $this->BuildInformation;
        $build->ProjectId = $this->projectid;
        $build->SetProject($this->Project);
        $build->SubmitTime = gmdate(FMT_DATETIME);

        // TODO: dark days lie in waiting for this...
        $build->StartTime = gmdate(FMT_DATETIME);

        $build->SetSubProject($this->SubProjectName);
        $build->GetIdFromName($this->SubProjectName);
        $build->RemoveIfDone();

        // If the build doesn't exist we add it
        if ($build->Id == 0) {
            $build->Append = $this->Append;
            $build->InsertErrors = false;
            add_build($build);
        } else {
            // Otherwise make sure that the build is up-to-date.
            $build->UpdateBuild($build->Id, -1, -1);

            // If the build already exists factor the number of tests
            // that have already been run into our running total.
            $this->NumberTestsFailed[$this->SubProjectName] += $build->GetNumberOfFailedTests();
            $this->NumberTestsNotRun[$this->SubProjectName] += $build->GetNumberOfNotRunTests();
            $this->NumberTestsPassed[$this->SubProjectName] += $build->GetNumberOfPassedTests();
        }

        $this->Builds[$this->SubProjectName] = $build;
    }

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->Builds);
    }
    /**
     * @return Build[]
     * @deprecated Use GetBuildCollection() 02/04/18
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
        foreach ($this->Builds as $build) {
            $collection->add($build);
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
        $preferences = $subscriber->getNotificationPreferences();
        if ($preferences->get(NotifyOn::TEST_FAILURE)) {
            $collection->add(new TestFailureTopic());
            $collection->add(new MissingTestTopic());
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

    /**
     * @return BuildGroup
     */
    public function GetBuildGroup()
    {
        $factory = $this->getModelFactory();
        $buildGroup = $factory->create(BuildGroup::class);
        foreach ($this->Builds as $build) {
            // TODO: this used to work:
            // $buildGroup->SetId($build->GroupId);
            $buildGroup->SetId($build->GetGroup());
            break;
        }
        return $buildGroup;
    }
}
