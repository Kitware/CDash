<?php
namespace CDash\Messaging\Subscription;

use CDash\Config;
use CDash\Messaging\Notification\NotificationInterface;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\SubscriberInterface;

class Subscription implements SubscriptionInterface
{
    protected static $max_display_items = 5;

    /** @var  SubscriberInterface $subscriber */
    private $subscriber;

    /** @var  TopicCollection $topicCollection */
    private $topicCollection;

    /** @var  NotificationInterface $notification */
    private $notification;

    /** @var  Project $project */
    private $project;

    /** @var  array $summary */
    private $summary;

    /** @var  Site $site */
    private $site;

    /**
     * @param SubscriberInterface $subscriber
     * @return Subscription
     */
    public function setSubscriber(SubscriberInterface $subscriber)
    {
        $this->subscriber = $subscriber;
        return $this;
    }

    /**
     * @param TopicCollection $topicCollection
     * @return Subscription
     */
    public function setTopicCollection(TopicCollection $topicCollection)
    {
        $this->topicCollection = $topicCollection;
        return $this;
    }

    /**
     * @return SubscriberInterface
     */
    public function getSubscriber()
    {
        return $this->subscriber;
    }

    /**
     * @return TopicCollection
     */
    public function getTopicCollection()
    {
        return $this->subscriber->getTopics();
    }

    /**
     * @return TopicCollection
     */
    public function getTopics()
    {
        return $this->getTopicCollection();
    }

    /**
     * @param NotificationInterface $notification
     * @return Subscription
     */
    public function setNotification(NotificationInterface $notification)
    {
        $this->notification = $notification;
        return $this;
    }

    /**
     * @return NotificationInterface;
     */
    public function getNotification()
    {
        return $this->notification;
    }

    /**
     * @return string
     */
    public function getSender()
    {
        return Config::getInstance()->get('CDASH_NOTIFICATION_SENDER');
    }

    /**
     * @return string
     */
    public function getRecipient()
    {
        return $this->subscriber->getAddress();
    }

    /**
     * @param Project $project
     * @return Subscription
     */
    public function setProject(Project $project)
    {
        $this->project = $project;
        return $this;
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param Site $site
     * @return $this
     */
    public function setSite(Site $site)
    {
        $this->site = $site;
        return $this;
    }

    /**
     * @return Site
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * @return int
     */
    public static function getMaxDisplayItems()
    {
        return self::$max_display_items;
    }

    /**
     * @param $max_display_items
     */
    public static function setMaxDisplayItems($max_display_items)
    {
        // $max_display_items must always have an integer value > zero
        if (is_int($max_display_items) && $max_display_items > 0) {
            self::$max_display_items = $max_display_items;
        }
    }

    /**
     * @return string[]
     */
    public function getBuildSummary()
    {
        if (!$this->summary) {
            $project = $this->project;
            $config = Config::getInstance();
            $summary = [];
            $topics = $this->subscriber->getTopics();
            $summary['topics'] = [];
            $summary['project_name'] = $project->GetName();
            $summary['project_url'] = "{$config->getBaseUrl()}/viewProject?projectid={$project->Id}";
            $summary['site_name'] = $this->site->Name;
            $summary['build_name'] = '';
            $summary['build_subproject_names'] = [];
            $summary['labels'] = [];
            $summary['build_time'] = '';
            $summary['build_type'] = '';
            $summary['build_parent_id'] = null;

            foreach ($topics as $topic) {
                $name = $topic->getTopicName();
                $summary['topics'][$name] = [
                    'description' => $topic->getTopicDescription(),
                    'count' => $topic->getTopicCount(),
                ];

                $builds = $topic->getBuildCollection();

                $summary['labels'] = array_merge($summary['labels'], $topic->getLabels());

                /** @var Build $build */
                foreach ($builds as $build) {
                    if ($build->SubProjectName) {
                        $summary['build_subproject_names'][] = $build->SubProjectName;
                    }

                    if (empty($summary['build_name'])) {
                        $summary['build_name'] = $build->Name;
                    }

                    if (empty($summary['build_time'])) {
                        $summary['build_time'] = $build->StartTime;
                    }

                    if (empty($summary['build_type'])) {
                        $summary['build_type'] = $build->Type;
                    }

                    if (is_null($summary['build_parent_id'])) {
                        $summary['build_parent_id'] = $build->GetParentId();
                    }
                }
            }
            $this->summary = $summary;
        }
        return $this->summary;
    }
}
