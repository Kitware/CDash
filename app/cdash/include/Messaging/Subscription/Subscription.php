<?php

namespace CDash\Messaging\Subscription;

use App\Models\Site;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use CDash\Model\SubscriberInterface;

class Subscription implements SubscriptionInterface
{
    protected static $max_display_items = 5;

    /** @var SubscriberInterface */
    private $subscriber;

    /** @var Project */
    private $project;

    /** @var array */
    private $summary;

    /** @var Site */
    private $site;

    /** @var BuildGroup */
    private $buildGroup;

    public function getBuildGroup()
    {
        return $this->buildGroup;
    }

    public function setBuildGroup(BuildGroup $buildGroup): static
    {
        $this->buildGroup = $buildGroup;
        return $this;
    }

    public function setSubscriber(SubscriberInterface $subscriber): static
    {
        $this->subscriber = $subscriber;
        return $this;
    }

    public function getSubscriber(): SubscriberInterface
    {
        return $this->subscriber;
    }

    public function getTopicCollection(): TopicCollection
    {
        return $this->subscriber->getTopics();
    }

    public function getRecipient(): string
    {
        return $this->subscriber->getAddress();
    }

    public function setProject(Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setSite(Site $site): self
    {
        $this->site = $site;
        return $this;
    }

    public static function getMaxDisplayItems(): int
    {
        return self::$max_display_items;
    }

    public static function setMaxDisplayItems($max_display_items): void
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

    public function getTopicDescriptions($case = null): array
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
     *                  TODO: A summary should be a BuildSummary class, create one asap
     */
    public function getBuildSummary(): array
    {
        if (!$this->summary) {
            $project = $this->project;
            $summary = [];
            $topics = $this->subscriber->getTopics();
            $summary['topics'] = [];
            $summary['build_group'] = $this->buildGroup->GetName();
            $summary['project_name'] = $project->GetName();
            $summary['project_url'] = url('/index.php') . "?project={$project->Name}";
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
                        $summary['build_summary_url'] = url("/builds/{$id}");
                    }
                }
            }
            $this->summary = $summary;
        }
        return $this->summary;
    }

    public function getTopicTemplates(): array
    {
        $templates = [];
        foreach ($this->subscriber->getTopics() as $topic) {
            // This seems like a good idea now but just wait.
            $templates = array_merge($templates, (array) $topic->getTemplate());
        }

        return array_unique($templates);
    }
}
