<?php
namespace CDash\Messaging\Topic;

use BuildGroup;
use CDash\Collection\BuildCollection;
use CDash\Collection\TestCollection;
use CDash\Messaging\Notification\Email\Decorator\TestFailureDecorator;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\ServiceContainer;
use CDash\Messaging\Topic\TestFailureTopic;

class TopicFactory
{
    /**
     * @param NotificationPreferences $preferences
     * @return TopicInterface[]
     */
    /*
    public static function createFrom(NotificationPreferences $preferences)
    {
        $topics = [];
        foreach ($preferences->getPropertyNames() as $topic) {
            if ($preferences->notifyOn($topic)) {
                $instance = self::create($topic);
                // Putting our subscription killer at the front of the queue
                // will prevent the unnecessary checking of other topics
                // should the topic not meet the specified criteria (e.g. not the author)
                if (is_a($instance, CancelationInterface::class)) {
                    array_unshift($topics, $instance);
                } else {
                    $topics[] = $instance;
                }
            }
        }
        return $topics;
    }
    */

    public static function createFrom(NotificationPreferences $preferences)
    {
        $topics = [];
        $container = ServiceContainer::getInstance();
        $settings = $preferences->getPropertyNames();

        foreach ($settings as $topic) {
            if ($preferences->notifyOn($topic)) {
                $class_name = __NAMESPACE__ . "\\{$topic}Topic";
                $instance = $container->create($class_name);
                // these topics are special cases to be handled later
                if (is_a($instance, DecoratableInterface::class)) {
                    $topics[] = $instance;
                }
            }
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
     * @return TopicInterface
     * @throws \Exception
     *
     * TODO: consider DI package
     */
    protected static function create($topicName)
    {
        switch ($topicName) {
            case 'AnyCheckinIssue':
                return new AnyCheckinIssueTopic();
            case 'BuildError':
                return new BuildErrorTopic();
            case 'BuildWarning':
                return new BuildWarningTopic();
            case 'CheckinIssueNightlyOnly':
                return new CheckinIssueNightlyOnlyTopic();
            case 'ConfigureError':
                return new ConfigureErrorTopic();
            case 'DynamicAnalysis':
                return new DynamicAnalysisTopic();
            case 'ExpectedSiteSubmitMissing':
                return new ExpectedSiteSubmitMissing();
            case 'Fixed':
                return new FixedTopic();
            case 'Label':
                return new LabeledTopic();
            case 'MyCheckinIssue':
                return new AuthoredTopic();
            case 'TestFailure':
                return new TestFailureTopic(new TestCollection());
            case 'UpdateError':
                return new UpdateErrorTopic();
            default:
                throw new \Exception("{$topicName} is not a known topic.");
        }
    }
}
