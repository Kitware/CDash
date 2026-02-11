<?php

namespace CDash\Messaging\Topic;

use CDash\Collection\BuildErrorCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Model\Build;
use CDash\Model\BuildFailure;
use CDash\Model\Subscriber;
use Illuminate\Support\Collection;

class BuildErrorTopic extends Topic implements Decoratable, Fixable, Labelable
{
    use IssueTemplateTrait;

    private $collection;
    private $type;
    private $diff;

    /**
     * When a user subscribes to receive notices for build errors or build warnings (and
     * similarly build failures) this method will return true if the user has not already
     * been notified for those events or if the has been notified but there are new events
     * not included in the previous notification.
     */
    public function subscribesToBuild(Build $build): bool
    {
        if ($this->subscriber) {
            $send_redundant = $this->subscriber->getNotificationPreferences()->get(NotifyOn::REDUNDANT);
            if ($send_redundant) {
                $function_name = 'GetNumberOf' . $this->getTopicDescription();
                if ($build->$function_name() > 0) {
                    return true;
                }
            }
        }

        $subscribe = false;
        $this->diff = $build->GetDiffWithPreviousBuild();
        if ($this->diff) {
            $type = $this->getTopicName();
            $subscribe = $this->diff[$type]['new'] > 0;
        }
        return $subscribe;
    }

    /**
     * @return Topic|void
     */
    public function setTopicData(Build $build)
    {
        $collection = $this->getTopicCollection();
        foreach ($build->Errors as $error) {
            if ($this->itemHasTopicSubject($build, $error)) {
                $collection->add($error);
            }
        }
    }

    public function getTopicCount(): int
    {
        $collection = $this->getTopicCollection();
        return $collection->count();
    }

    /**
     * @return bool
     *              // TODO: refactor itemHasTopicSubject, remove callables from Topic and subclasses & remove Build from signature
     */
    public function itemHasTopicSubject(Build $build, $item): bool
    {
        return $item->Type === $this->type;
    }

    public function getTopicCollection(): BuildErrorCollection
    {
        if (!$this->collection) {
            $this->collection = new BuildErrorCollection();
        }
        return $this->collection;
    }

    /**
     * @return $this
     */
    public function setType($type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTopicName(): string
    {
        return $this->type == Build::TYPE_ERROR ? Topic::BUILD_ERROR : Topic::BUILD_WARNING;
    }

    public function getTopicDescription(): string
    {
        return $this->type === Build::TYPE_ERROR ? 'Errors' : 'Warnings';
    }

    public function hasFixes(): bool
    {
        $key = $this->getTopicName();
        return $this->diff && $this->diff[$key]['fixed'] > 0;
    }

    public function getFixes(): array
    {
        $key = $this->getTopicName();
        if ($this->diff) {
            return $this->diff[$key];
        }
        return [];
    }

    public function getLabelsFromBuild(Build $build): Collection
    {
        $collection = collect();
        if (isset($build->Errors)) {
            /** @var BuildFailure $error */
            foreach ($build->Errors as $error) {
                if (is_a($error, BuildFailure::class)
                && $this->itemHasTopicSubject($build, $error)) {
                    foreach ($error->Labels as $label) {
                        $collection->put($label->Text, $label);
                    }
                }
            }
        }

        return $collection;
    }

    public function setTopicDataWithLabels(Build $build, Collection $labels): void
    {
        // We've already determined that the build has the subscribed labels
        // so here we can just use setTopicData
        $this->setTopicData($build);
    }

    public function isSubscribedToBy(Subscriber $subscriber): bool
    {
        $subscribes = false;
        $preferences = $subscriber->getNotificationPreferences();

        if ($this->type === Build::TYPE_ERROR && $preferences->get(NotifyOn::BUILD_ERROR)) {
            $subscribes = true;
        }

        if ($this->type === Build::TYPE_WARN && $preferences->get(NotifyOn::BUILD_WARNING)) {
            $subscribes = true;
        }

        // TODO: investigate whether or not this is necessary
        if ($preferences->get(NotifyOn::FIXED)) {
            $subscribes = true;
        }

        return $subscribes;
    }
}
