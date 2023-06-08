<?php
namespace CDash\Messaging\Subscription;

use CDash\Config;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use App\Models\Site;
use CDash\Model\SubscriberInterface;

class Subscription implements SubscriptionInterface
{
    protected static $max_display_items = 5;

    /** @var  SubscriberInterface $subscriber */
    private $subscriber;

    /** @var  Project $project */
    private $project;

    /** @var  array $summary */
    private $summary;

    /** @var  Site $site */
    private $site;

    /** @var BuildGroup $buildGroup */
    private $buildGroup;

    public function getBuildGroup()
    {
        return $this->buildGroup;
    }

    public function setBuildGroup(BuildGroup $buildGroup)
    {
        $this->buildGroup = $buildGroup;
        return $this;
    }

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

    public function setSite(Site $site): self
    {
        $this->site = $site;
        return $this;
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

    public function getProjectName()
    {
        return $this->project->GetName();
    }

    public function getTopicDescriptions($case = null)
    {
        $descriptions = [];
        foreach ($this->subscriber->getTopics() as $topic) {
            $description = $topic->getTopicDescription();
            if (!is_null($case)) {
                $description = $case === CASE_UPPER ? strtoupper($description) : strtolower($description);
            }
            $descriptions[] = $description;
        }
        return $descriptions;
    }

    /**
     * @return string[]
     * TODO: A summary should be a BuildSummary class, create one asap
     */
    public function getBuildSummary()
    {
        if (!$this->summary) {
            $project = $this->project;
            $config = Config::getInstance();
            $baseUrl = $config->getBaseUrl();
            $summary = [];
            $topics = $this->subscriber->getTopics();
            $summary['topics'] = [];
            $summary['build_group'] = $this->buildGroup->GetName();
            $summary['project_name'] = $project->GetName();
            $summary['project_url'] = "{$baseUrl}/index.php?project={$project->Name}";
            $summary['site_name'] = $this->site->name;
            $summary['build_name'] = '';
            $summary['build_subproject_names'] = [];
            $summary['labels'] = [];
            $summary['build_time'] = '';
            $summary['build_type'] = '';
            $summary['build_parent_id'] = null;
            $summary['build_summary_url'] = null;
            $summary['fixes'] = [];
            $checkForFixed = $this->subscriber
                ->getNotificationPreferences()
                ->notifyOn('Fixed');

            foreach ($topics as $topic) {
                $name = $topic->getTopicName();

                if ($checkForFixed) {
                    $summary['fixes'] = $topic->getFixed();
                }

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

                    if (is_null($summary['build_summary_url'])) {
                        $id = (int) $summary['build_parent_id'] ?: $build->Id;
                        $summary['build_summary_url'] = "{$baseUrl}/build/{$id}";
                    }
                }
            }
            $this->summary = $summary;
        }
        return $this->summary;
    }

    /**
     * @return array
     */
    public function getTopicTemplates()
    {
        $templates = [];
        foreach ($this->subscriber->getTopics() as $topic) {
            // This seems like a good idea now but just wait.
            $templates = array_merge($templates, (array)$topic->getTemplate());
        }

        return array_unique($templates);
    }
}
