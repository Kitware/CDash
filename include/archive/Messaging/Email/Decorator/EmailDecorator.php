<?php
namespace CDash\archive\Messaging\Email\Decorator;

use CDash\Messaging\Collection\Collection;
use CDash\Messaging\Email\EmailMessage;
use CDash\Messaging\Collection\RecipientCollection;
use CDash\Messaging\MessageInterface;

use LabelEmail;
use UserProject;

abstract class EmailDecorator implements EmailDecoratorInterface
{
    const TOPIC_CONFIGURE = 'CONFIGURE';
    const TOPIC_DYNAMIC_ANALYSIS = 'DYNAMIC_ANALYSIS';
    const TOPIC_ERROR = 'BUILD_ERROR';
    const TOPIC_TEST = 'TEST';
    const TOPIC_UPDATE = 'UPDATE';
    const TOPIC_WARNING = 'BUILD_WARNING';

    const TOPIC_VALUE = [
        self::TOPIC_DYNAMIC_ANALYSIS => 64,
        self::TOPIC_TEST => 32,
        self::TOPIC_ERROR => 16,
        self::TOPIC_WARNING => 8,
        self::TOPIC_CONFIGURE => 4,
        self::TOPIC_UPDATE => 2,
    ];

    /** @var EmailMessage $message */
    protected $message;

    /** @var  Collection $topicCollection */
    protected $topicCollection;

    /** @var  RecipientCollection $recipientCollection */
    protected $recipientCollection;

    /**
     * EmailDecoratorInterface constructor.
     * @param Collection|null $topicCollection
     * @param RecipientCollection|null $recipientCollection
     */
    public function __construct(
        Collection $topicCollection = null,
        RecipientCollection $recipientCollection = null
    ) {
        $this->topicCollection = $topicCollection;
        $this->recipientCollection = $recipientCollection;
    }

    /**
     * @param EmailMessage $message
     * @return void
     */
    public function setMessage(MessageInterface $message)
    {
        $this->message = $message;
    }

    /**
     * @return Collection
     */
    public function getTopicCollection()
    {
        $this->topicCollection->rewind();
        return $this->topicCollection;
    }

    /**
     * @return RecipientCollection
     */
    public function getRecipientCollection()
    {
        if (!$this->recipientCollection) {
            $this->recipientCollection = new RecipientCollection();
        }
        return $this->recipientCollection;
    }

    /**
     * @param $topicType
     * @param $userSetting
     * @return bool
     */
    protected function isUserSubscribedToTopic($topicType, $userSetting)
    {
        foreach (self::TOPIC_VALUE as $type => $setting) {
            if ($userSetting >= $setting) {
                if ($topicType === $type) {
                    return true;
                }
                $userSetting -= $setting;
            }
        }
        return false;
    }

    protected function isUserSubscribedToLabels(UserProject $userProject)
    {
        $project = $this->message->getProject();
        $labelEmail = new LabelEmail();
        $labelEmail->ProjectId = $project->Id;
        $labelEmail->UserId = $userProject->UserId;
        $labels = $labelEmail->GetLabels();

        if (count($labels) === 0) {
            return true;
        }

        return $this->hasLabels($labels);
    }

    /**
     * @param \UserProject $user
     * @return bool
     */
    private function isBuildAuthoredByUser($email)
    {
        foreach ($this->message->getBuilds() as $label => $build) {
            if ($build->buildHasAuthor($email)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    private function isBuildNightly()
    {
        $build_group = $this->message->getBuildGroup();
        return $build_group->GetType() === BuildGroup::TYPE_NIGHTLY;
    }

    /**
     * @return bool
     */
    public function hasRecipients()
    {
        $recipients = $this->getRecipientCollection();
        $users = $this->message->getSubscribers();
        $topic = $this->getTopicName();

        /** @var \UserProject $user */
        foreach ($users as $email => $user) {

            // the EmailType property on the UserProject object has one of four values
            //   0 = never email the user
            //   1 = when the user's build contains subscribed topic
            //   2 = when the nightly build from any user contains subscribed topic
            //   3 = when any build from any user contains subscribed topic

            switch($user->EmailType)
            {
                case UserProject::EMAIL_NEVER:
                    continue 2;
                case UserProject::EMAIL_USER_BUILD_HAS_TOPIC:
                    // check to see if build comes from author
                    if (!$this->isBuildAuthoredByUser($user)) {
                        continue 2;
                    }
                    break;
                case UserProject::EMAIL_NIGHTLY_BUILD_HAS_TOPIC:
                    // check to see if build is Nightly
                    if (!$this->isBuildNightly()) {
                        continue 2;
                    }
                    break;
                case UserProject::EMAIL_ANY_BUILD_HAS_TOPIC:
                    // noop, here for documentation sake only
                    break;
            }

            if (!$this->isUserSubscribedToTopic($topic, $user->EmailCategory)) {
                continue;
            }

            /* TODO: Not sure how this works, confer with Zack
            if (!$this->isUserSubscribedToLabels($user)) {
                continue;
            }
            */

            $recipients->add($user, $email);
        }

        return count($this->recipientCollection) > 0;
    }
}
