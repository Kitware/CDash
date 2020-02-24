<?php
namespace CDash\Messaging\Topic;

use Illuminate\Support\Collection;

use CDash\Collection\BuildErrorCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Model\Build;
use CDash\Model\BuildFailure;
use CDash\Model\SubscriberInterface;

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
     *
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $subscribe = false;
        $this->diff = $build->GetDiffWithPreviousBuild();
        if ($this->diff) {
            $type = $this->getTopicName();
            $subscribe = $this->diff[$type]['new'] > 0;
        }
        return $subscribe;
    }

    /**
     * @param Build $build
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

    /**
     * @return int
     */
    public function getTopicCount()
    {
        $collection = $this->getTopicCollection();
        return $collection->count();
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     * // TODO: refactor itemHasTopicSubject, remove callables from Topic and subclasses & remove Build from signature
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        return $item->Type === $this->type;
    }

    /**
     * @return BuildErrorCollection|\CDash\Collection\CollectionInterface
     */
    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = new BuildErrorCollection();
        }
        return $this->collection;
    }

    /**
     * @param $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    public function getTopicName()
    {
        return $this->type == Build::TYPE_ERROR ? Topic::BUILD_ERROR : Topic::BUILD_WARNING;
    }

    public function getTopicDescription()
    {
        return $this->type === Build::TYPE_ERROR ? 'Errors' : 'Warnings';
    }

    /**
     * @return bool
     */
    public function hasFixes()
    {
        $key = $this->getTopicName();
        return $this->diff && $this->diff[$key]['fixed'] > 0;
    }

    /**
     * @return array
     */
    public function getFixes()
    {
        $key = $this->getTopicName();
        if ($this->diff) {
            return $this->diff[$key];
        }
    }

    /**
     * @param Build $build
     * @return Collection
     */
    public function getLabelsFromBuild(Build $build)
    {
        $collection = collect();
        if (isset($build->Errors)) {
            /** @var BuildFailure $error */
            foreach ($build->Errors as $error) {
                if (is_a($error, BuildFailure::class)
                && $this->itemHasTopicSubject($build, $error)) {
                    foreach ($error->Labels as $label) {
                        $collection->push($label);
                    }
                }
            }
        }

        return $collection;
    }

    /**
     * @param Build $build
     * @param Collection $labels
     * @return void
     */
    public function setTopicDataWithLabels(Build $build, Collection $labels)
    {
        // We've already determined that the build has the subscribed labels
        // so here we can just use setTopicData
        $this->setTopicData($build);
    }

    /**
     * @param SubscriberInterface $subscriber
     * @return bool
     */
    public function isSubscribedToBy(SubscriberInterface $subscriber)
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
