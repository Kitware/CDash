<?php

namespace App\Http\Submission\Handlers;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\TestMeasurement;
use App\Utils\SubmissionUtils;
use App\Utils\TestCreator;
use CDash\Collection\BuildCollection;
use CDash\Collection\SubscriptionBuilderCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\MissingTestTopic;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\Image;
use CDash\Model\Label;
use CDash\Model\Project;
use CDash\Model\SubscriberInterface;
use CDash\Submission\CommitAuthorHandlerInterface;
use CDash\Submission\CommitAuthorHandlerTrait;

class TestingHandler extends AbstractXmlHandler implements ActionableBuildInterface, CommitAuthorHandlerInterface
{
    use CommitAuthorHandlerTrait;
    use UpdatesSiteInformation;

    protected static ?string $schema_file = '/app/Validators/Schemas/Test.xsd';
    private $StartTimeStamp;
    private $EndTimeStamp;

    private $TestMeasurement;
    private $Label;

    // TODO: Evaluate whether this is needed
    private $Labels;

    private $TestCreator;

    /** @var Build[] Builds */
    private $Builds;
    private array $BuildInformation;

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
    public function __construct(Project $project)
    {
        parent::__construct($project);
        $this->Builds = [];
        $this->SubProjects = [];
        $this->NumberTestsFailed = [];
        $this->NumberTestsNotRun = [];
        $this->NumberTestsPassed = [];
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
    }

    /** Start Element */
    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);
        $factory = $this->getModelFactory();

        if ($this->currentPathMatches('site')) {
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation();
            $this->BuildInformation = [];
            $this->BuildName = '';
            $this->BuildStamp = '';
            $this->SubProjectName = '';
            $this->Generator = '';
            $this->PullRequest = '';

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
                    switch ($key) {
                        case 'OSNAME':
                            $this->BuildInformation['osname'] = $value;
                            break;
                        case 'OSRELEASE':
                            $this->BuildInformation['osrelease'] = $value;
                            break;
                        case 'OSVERSION':
                            $this->BuildInformation['osversion'] = $value;
                            break;
                        case 'OSPLATFORM':
                            $this->BuildInformation['osplatform'] = $value;
                            break;
                        case 'COMPILERNAME':
                            $this->BuildInformation['compilername'] = $value;
                            break;
                        case 'COMPILERVERSION':
                            $this->BuildInformation['compilerversion'] = $value;
                            break;
                    }
                }
            }

            if (empty($this->BuildName)) {
                $this->BuildName = '(empty)';
            }
            $this->updateSiteInfoIfChanged($this->Site, $siteInformation);
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = [];
                $this->createBuild();
            }
        } elseif ($name == 'TEST' && count($attributes) > 0) {
            $this->TestCreator = new TestCreator();
            $this->TestCreator->projectid = $this->GetProject()->Id;
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
        } elseif ($name === 'VALUE' && $this->getParent() === 'MEASUREMENT') {
            if (isset($attributes['COMPRESSION']) && $attributes['COMPRESSION'] == 'gzip') {
                $this->TestCreator->alreadyCompressed = true;
            }
        } elseif ($name === 'LABEL' && $this->getParent() === 'LABELS') {
            $this->Label = $factory->create(Label::class);
        }
    }

    /** End Element */
    public function endElement($parser, $name): void
    {
        $factory = $this->getModelFactory();

        if ($name === 'TEST' && $this->getParent() === 'TESTING') {
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
            $this->TestCreator->projectid = $this->GetProject()->Id;
            $this->TestCreator->create($build);
        } elseif ($name === 'LABEL' && $this->getParent() == 'LABELS') {
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
                if (str_contains($this->TestMeasurement->type, 'image')) {
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
        } elseif ($this->currentPathMatches('site')) {
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
                $build->UpdateTestNumbers((int) $this->NumberTestsPassed[$subproject],
                    (int) $this->NumberTestsFailed[$subproject],
                    (int) $this->NumberTestsNotRun[$subproject]);

                // Is it really necessary to have to load the build from the db here?
                $build->ComputeTestTiming();

                if ($this->StartTimeStamp > 0 && $this->EndTimeStamp > 0) {
                    // Update test duration in the Build table.
                    $duration = $this->EndTimeStamp - $this->StartTimeStamp;
                    $build->UpdateTestDuration((int) $duration, !$all_at_once);
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

        parent::endElement($parser, $name);
    }

    /** Text function */
    public function text($parser, $data): void
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
                    $this->TestCreator->testName .= $data;
                    break;
                case 'PATH':
                    $this->TestCreator->testPath .= $data;
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
            $this->SubProjects[$this->SubProjectName][] = $data;
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

    public function getBuildStamp(): string
    {
        return $this->BuildStamp;
    }

    public function getBuildName(): string
    {
        return $this->BuildName;
    }

    public function getSubProjectName()
    {
        return $this->SubProjectName;
    }

    private function createBuild(): void
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
        /** @var Build $build */
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
        $build->OSName = $this->BuildInformation['osname'] ?? null;
        $build->OSRelease = $this->BuildInformation['osrelease'] ?? null;
        $build->OSVersion = $this->BuildInformation['osversion'] ?? null;
        $build->OSPlatform = $this->BuildInformation['osplatform'] ?? null;
        $build->CompilerName = $this->BuildInformation['compilername'] ?? null;
        $build->CompilerVersion = $this->BuildInformation['compilerversion'] ?? null;
        $build->ProjectId = $this->GetProject()->Id;
        $build->SetProject($this->GetProject());
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
            SubmissionUtils::add_build($build);
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

    public function GetBuildCollection(): BuildCollection
    {
        $collection = new BuildCollection();
        foreach ($this->Builds as $build) {
            $collection->add($build);
        }
        return $collection;
    }

    public function GetTopicCollectionForSubscriber(SubscriberInterface $subscriber): TopicCollection
    {
        $collection = new TopicCollection();
        $preferences = $subscriber->getNotificationPreferences();
        if ($preferences->get(NotifyOn::TEST_FAILURE)) {
            $collection->add(new TestFailureTopic());
            $collection->add(new MissingTestTopic());
        }
        return $collection;
    }

    public function GetSubscriptionBuilderCollection(): SubscriptionBuilderCollection
    {
        $collection = (new SubscriptionBuilderCollection())
            ->add(new UserSubscriptionBuilder($this))
            ->add(new CommitAuthorSubscriptionBuilder($this));
        return $collection;
    }

    public function GetBuildGroup(): BuildGroup
    {
        $buildGroup = new BuildGroup();
        foreach ($this->Builds as $build) {
            // TODO: this used to work:
            // $buildGroup->SetId($build->GroupId);
            $buildGroup->SetId($build->GetGroup());
            break;
        }
        return $buildGroup;
    }
}
