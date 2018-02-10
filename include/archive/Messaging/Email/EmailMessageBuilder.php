<?php
namespace CDash\archive\Messaging\Email;

use ActionableBuildInterface;
use BuildGroup;
use CDash\Messaging\BuilderInterface;
use CDash\Messaging\Collection\BuildCollection;
use CDash\Messaging\Collection\BuildErrorCollection;
use CDash\Messaging\Collection\BuildFailureCollection;
use CDash\Messaging\Collection\DecoratorCollection;
use CDash\Messaging\Collection\RecipientCollection;
use CDash\Messaging\Collection\TestFailureCollection;
use CDash\Messaging\Email\Decorator\BuildErrorEmailDecorator;
use CDash\Messaging\Email\Decorator\BuildFailureErrorsEmailDecorator;
use CDash\Messaging\Email\Decorator\BuildFailureWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\BuildWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\ConfigureErrorsEmailDecorator;
use CDash\Messaging\Email\Decorator\DynamicAnalysisEmailDecorator;
use CDash\Messaging\Email\Decorator\MissingTestsEmailDecorator;
use CDash\Messaging\Email\Decorator\TestFailuresEmailDecorator;
use CDash\Messaging\Email\Decorator\TestWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\UpdateErrorsEmailDecorator;
use CDash\Messaging\MessageInterface;
use Project;

/**
 * Class EmailMessageBuilder
 * @package CDash\Messaging\Email
 */
class EmailMessageBuilder implements BuilderInterface
{
    /** @var ActionableBuildInterface $actionableBuild */
    private $actionableBuild;

    /** @var MessageInterface $message */
    private $message;

    /**
     * BuilderInterface constructor.
     * @param ActionableBuildInterface $actionableBuild
     */
    public function __construct(ActionableBuildInterface $actionableBuild)
    {
        $this->actionableBuild = $actionableBuild;
    }

    /**
     * @return BuilderInterface
     */
    public function createMessage()
    {
        $decoratorCollection = new DecoratorCollection();

        $this->message = new EmailMessage($decoratorCollection);
        return $this;
    }

    /**
     * @return \CDash\Messaging\MessageInterface
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return BuilderInterface
     */
    public function addProject()
    {
        $project = new Project();
        $project->Id = $this->actionableBuild->getProjectId();
        $this->message->setProject($project);
        return $this;
    }

    /**
     * @return BuilderInterface
     */
    public function addBuildGroup()
    {
        $buildGroup = new BuildGroup();
        $buildGroup->SetId($this->actionableBuild->getBuildGroupId());
        $this->message->setBuildGroup($buildGroup);
        return $this;
    }

    /**
     * @return BuilderInterface
     */
    public function addBuildCollection()
    {
        $buildCollection = new BuildCollection();
        foreach ($this->actionableBuild->getActionableBuilds() as $name => $build) {
            $buildCollection->add($build, $name);
        }
        $this->message->setBuildCollection($buildCollection);
        return $this;
    }

    /**
     * @return BuilderInterface
     */
    public function addDecoratorsToCollection()
    {
        $buildGroup = $this->message->getBuildGroup();
        $summary = $buildGroup->GetSummaryEmail();

        if ($summary === BuildGroup::EMAIL_SUMMARY) {
            $this->message
                ->addDecorator(new BuildErrorEmailDecorator(
                    new BuildErrorCollection(),
                    new RecipientCollection()
                ))
                ->addDecorator(new BuildWarningsEmailDecorator(
                    new BuildErrorCollection(),
                    new RecipientCollection()
                ))
                ->addDecorator(new BuildFailureErrorsEmailDecorator(
                    new BuildFailureCollection(),
                    new RecipientCollection()
                ))
                ->addDecorator(new BuildFailureWarningsEmailDecorator(
                    new BuildFailureCollection(),
                    new RecipientCollection()
                ))
                ->addDecorator(new ConfigureErrorsEmailDecorator())
                ->addDecorator(new DynamicAnalysisEmailDecorator())
                ->addDecorator(new TestFailuresEmailDecorator())
                ->addDecorator(new TestWarningsEmailDecorator())
                ->addDecorator(new MissingTestsEmailDecorator())
                ->addDecorator(new UpdateErrorsEmailDecorator());

        } else {
            switch ($this->actionableBuild->getType()) {
                case ActionableBuildInterface::TYPE_BUILD:
                    $this->message
                        ->addDecorator(new BuildErrorEmailDecorator(
                            new BuildErrorCollection(),
                            new RecipientCollection()
                        ))
                        ->addDecorator(new BuildWarningsEmailDecorator(
                            new BuildErrorCollection(),
                            new RecipientCollection()
                        ))
                        ->addDecorator(new BuildFailureErrorsEmailDecorator(
                            new BuildFailureCollection(),
                            new RecipientCollection()
                        ))
                        ->addDecorator(new BuildFailureWarningsEmailDecorator(
                            new BuildFailureCollection(),
                            new RecipientCollection()
                        ));
                    break;
                case ActionableBuildInterface::TYPE_CONFIGURE:
                    $this->message
                        ->addDecorator(new ConfigureErrorsEmailDecorator());
                    break;
                case ActionableBuildInterface::TYPE_DYNAMIC_ANALYSIS:
                    $this->message
                        ->addDecorator(new DynamicAnalysisEmailDecorator());
                    break;
                case ActionableBuildInterface::TYPE_TEST:
                    $this->message
                        ->addDecorator(new TestFailuresEmailDecorator(
                            new TestFailureCollection(),
                            new RecipientCollection()
                        ))
                        ->addDecorator(new TestWarningsEmailDecorator())
                        ->addDecorator(new MissingTestsEmailDecorator());
                    break;
                case ActionableBuildInterface::TYPE_UPDATE:
                    $this->message
                        ->addDecorator(new UpdateErrorsEmailDecorator());
                    break;
            }
        }
        return $this;
    }
}
