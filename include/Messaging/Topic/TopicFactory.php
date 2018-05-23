<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Collection\BuildCollection;
use CDash\Collection\TestCollection;
use CDash\Messaging\Notification\Email\Decorator\TestFailureDecorator;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\ServiceContainer;
use CDash\Messaging\Topic\TestFailureTopic;

class TopicFactory
{
    public static function createFrom(NotificationPreferences $preferences)
    {
        $topics = [];
        $settings = $preferences->getPropertyNames();

        foreach ($settings as $topic) {
            if ($preferences->notifyOn($topic)) {
                $instance = self::create($topic);
                if ($instance) {
                    $topics[] = $instance;
                }
            }
        }

        if ($preferences->get(NotifyOn::ONCE)) {
            $decorated = [];
            foreach ($topics as $topic) {
                $decorated[] = new EmailSentTopic($topic);
            }
            $topics = $decorated;
        }

        if ($preferences->get(NotifyOn::GROUP_NIGHTLY)) {
            $decorated = [];
            foreach ($topics as $topic) {
                $groupTopic = new GroupMembershipTopic($topic);
                $groupTopic->setGroup(BuildGroup::NIGHTLY);
                $decorated[] = $groupTopic;
            }
            $topics = $decorated;
        }

        if ($preferences->get(NotifyOn::AUTHORED)) {
            $decorated = [];
            foreach ($topics as $topic) {
                // do not decorate LabeledTopic
                if (get_class($topic) === LabeledTopic::class) {
                    $decorated[] = $topic;
                    continue;
                }
                $decorated[] = new AuthoredTopic($topic);
            }
            $topics = $decorated;
        }

        return $topics;
    }

    /**
     * Hard coding these like this is a somewhat of a bummer but is advantageous in that
     * should we need to inject dependencies into our topics we may do so here. (Mainly
     * considering the future and what the FilterTopic might look like.)
     *
     * @param $topicName
     * @return TopicInterface|null
     */
    protected static function create($topicName)
    {
        switch ($topicName) {
            case 'BuildError':
                $topic = new BuildErrorTopic();
                $topic->setType(Build::TYPE_ERROR);
                return $topic;
            case 'BuildWarning':
                $topic = new BuildErrorTopic();
                $topic->setType(Build::TYPE_WARN);
                return $topic;
            case 'Configure':
                return new ConfigureTopic();
            case 'DynamicAnalysis':
                return new DynamicAnalysisTopic();
            case 'ExpectedSiteSubmitMissing':
                return new ExpectedSiteSubmitMissing();
            case 'Fixed':
                return new FixedTopic();
            case 'Labeled':
                return new LabeledTopic();
            case 'TestFailure':
                return new TestFailureTopic();
            case 'UpdateError':
                return new UpdateErrorTopic();
            default:
                return null;
        }
    }
}
