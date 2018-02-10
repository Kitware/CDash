<?php
namespace CDash\Messaging\Topic;

use CDash\Messaging\Preferences\NotificationPreferences;

class TopicFactory
{
    /**
     * @param NotificationPreferences $preferences
     * @return TopicInterface[]
     */
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
                return new LabelTopic();
            case 'MyCheckinIssue':
                return new MyCheckinIssueTopic();
            case 'TestFailure':
                return new TestFailureTopic();
            case 'UpdateError':
                return new UpdateErrorTopic();
            default:
                throw new \Exception("{$topicName} is not a known topic.");
        }
    }
}
