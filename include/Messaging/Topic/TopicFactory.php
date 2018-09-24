<?php
namespace CDash\Messaging\Topic;

use ActionableBuildInterface;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Singleton;
use Exception;
use UpdateHandler;

/**
 * Class TopicFactory
 * @package CDash\Messaging\Topic
 *
 * Given NotificationPreferences TopicFactory::createFrom will return an array of decorated
 * topics.
 *
 * Topics come in two flavors--regular topics and decorate-able topics. What follows is a
 * discussion of the distinction.
 *
 * Decorate-able topics can be thought of as fundamental topics, e.g. DynamicAnalysisTopic,
 * BuildErrorTopic, or TestFailureTopic and they represent the type of build (that is, a phase
 * in the build process such as the dynamic analysis, the actual build itself or testing)
 * represented by the xml submitted to CDash by CTest. If a user wants to receive a notification
 * about a test failure the return of this method will include a TestFailureTopic.
 *
 * Note that a TestFailureTopic is decorate-able. Consider the other preferences that may be
 * present in addition to the test failure:
 *   * A user may want to receive notification of test failures only once.
 *   * A user may want to receive notifications of test failures only if user is the author of a
 *     change that prompted the failure.
 *   * A user may not want to receive multiple notifications of test failure, yet receive a
 *     notification when the test has been fixed.
 *
 * At any given time, it's possible for any combination of these preferences to co-exist, thus the
 * reason for the distinction between regular and decorate-able topics. For instance given the
 * following scenario:
 *
 *   * A user is subscribed to DynamicAnalysis submissions
 *   * The user only wishes to see those DynamicAnalysis submissions from the Nightly submissions
 *   * The user wishes to see all DynamicAnalysis submissions regardless of authorship
 *
 * Included in the return from this method will be a DynamicAnalysisTopic decorated by a
 * GroupMemberShipTopic.
 *
 * In another scenario:
 *
 *   * A user is subscribed to test phase submissions with failures
 *   * User only wishes to see failures authored by his or herself
 *   * User does not wish to receive further notifications after the original
 *   * User does wish to receive a notification authored failure was fixed
 *
 * Included in the return from this method will be a TestFailureTopic decorated with an Authored-
 * Topic, an EmailSentTopic, FixedTopic.
 */
class TopicFactory extends Singleton
{
    public static function create(NotificationPreferences $preferences, ActionableBuildInterface $handler)
    {
        $self = self::getInstance();
        $topics = new TopicCollection();

        switch (get_class($handler)) {
            case 'BuildHandler':
                $self->setBuildHandlerTopics($preferences, $topics);
                break;
            case 'ConfigureHandler':
                $self->setConfigureHandlerTopics($preferences, $topics);
                break;
            case 'DynamicAnalysisHandler':
                $self->setDynamicAnalysisHandlerTopics($preferences, $topics);
                break;
            case 'TestingHandler':
                $self->setTestingHandlerTopics($preferences, $topics);
                break;
            case 'UpdateHandler':
                $self->setUpdateHandlerTopics($preferences, $topics);
                break;
        }

        $self->setFixables($preferences, $topics);
        $self->setLabelables($preferences, $topics);
        $self->setDeliverables($preferences, $topics);
        $self->setGroupFilterables($preferences, $topics);
        $self->setAttributables($preferences, $topics);

        return $topics;
    }

    /**
     * @param NotificationPreferences $preferences
     * @param TopicCollection $topics
     * @return void
     */
    public function setBuildHandlerTopics(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::BUILD_ERROR)) {
            $topics->add(new BuildErrorTopic());
        }

        if ($preferences->get(NotifyOn::BUILD_WARNING)) {
            $topic = new BuildErrorTopic();
            $topic->setType(Build::TYPE_WARN);
            $topics->add($topic);
        }
    }

    protected function setConfigureHandlerTopics(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::CONFIGURE)) {
            $topics->add(new ConfigureTopic());
        }
    }

    protected function setDynamicAnalysisHandlerTopics(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::DYNAMIC_ANALYSIS)) {
            $topics->add(new DynamicAnalysisTopic());
        }
    }

    protected function setTestingHandlerTopics(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::TEST_FAILURE)) {
            $topics->add(new TestFailureTopic());
            $topics->add(new MissingTestTopic());
        }
    }

    protected function setUpdateHandlerTopics(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::UPDATE_ERROR)) {
            $topics->add(new UpdateErrorTopic());
        }

        if ($preferences->get(NotifyOn::FIXED)) {
            $this->setBuildHandlerTopics($preferences, $topics);
            $this->setTestingHandlerTopics($preferences, $topics);
        }
    }

    protected function setFixables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::FIXED)) {
            foreach ($topics as $topic) {
                if (is_a($topic, Fixable::class)) {
                    $fixable = new FixedTopic($topic);
                    $topics->add($fixable);
                }
            }
        }
    }

    protected function setLabelables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::LABELED)) {
            foreach ($topics as $topic) {
                if (is_a($topic, Labelable::class)) {
                    $labelable = new LabeledTopic($topic);
                    $topics->add($labelable);
                }
            }
        }
    }

    protected function setDeliverables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if (!$preferences->get(NotifyOn::REDUNDANT)) {
            foreach ($topics as $topic) {
                $deliverable = new EmailSentTopic($topic);
                $topics->add($deliverable);
            }
        }
    }

    protected function setGroupFilterables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::GROUP_NIGHTLY)) {
            foreach ($topics as $topic) {
                $group = new GroupMembershipTopic($topic);
                $group->setGroup(BuildGroup::NIGHTLY);
                $topics->add($group);
            }
        }
    }

    protected function setAttributables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::AUTHORED)) {
            foreach ($topics as $topic) {
                if (!is_a($topic, LabeledTopic::class)) {
                    $attributable = new AuthoredTopic($topic);
                    $topics->add($attributable);
                }
            }
        }
    }

    /**
     * @param NotificationPreferences $preferences
     * @return array
     */
    public static function createFrom(NotificationPreferences $preferences)
    {
        $topics = [];
        $settings = $preferences->getPropertyNames();
        $onFixed = $preferences->notifyOn('Fixed');
        $onLabeled = $preferences->notifyOn('Labeled');

        foreach ($settings as $topic) {
            if ($preferences->notifyOn($topic)) {
                $instance = self::create($topic);
                if ($instance) {
                    if ($onFixed && $instance->isA(Fixable::class)) {
                        $instance = new FixedTopic($instance);
                    }
                    if ($onLabeled && $instance->isA(Labelable::class)) {
                        $instance = new LabeledTopic($instance);
                    }
                    $topics[] = $instance;
                }
            }
        }

        // because you cannot subscribe to Missing Tests build here if
        // subscribed to TestFailures
        if ($preferences->notifyOn('TestFailure')) {
            $topic = new MissingTestTopic();
            if ($onLabeled) {
                $topic = new LabeledTopic($topic);
            }
            $topics[] = $topic;
        }

        // Start decoration of Topics
        if (!$preferences->get(NotifyOn::REDUNDANT)) {
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
     * @param $topicName
     * @return TopicInterface|null
     */
    /*
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
            case 'TestFailure':
                return new TestFailureTopic();
            case 'UpdateError':
                return new UpdateErrorTopic();
            default:
                return null;
        }
    }
    */
}
