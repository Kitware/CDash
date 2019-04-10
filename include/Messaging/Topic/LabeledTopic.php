<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\BuildCollection;
use CDash\Collection\BuildErrorCollection;
use CDash\Model\ActionableTypes;
use CDash\Model\Build;
use CDash\Collection\Collection;
use CDash\Collection\CollectionCollection;
use CDash\Collection\ConfigureCollection;
use CDash\Collection\LabelCollection;
use CDash\Collection\TestCollection;

class LabeledTopic extends Topic implements Decoratable
{
    /** @var  CollectionCollection $labeledCollection */
    protected $topicCollection;

    private $decoratedSubscribes;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        /*
         * The current logic/requirement is that if you are subscribed to labels
         * your build must contain that label for you to receive an notification
         * despite the "when any checkins are causing problems in any sections"
         * preference being set. The difference that makes here is && vs. ||, so
         * the decorated topic must subscribeToBuild and this also must find a
         * label the user is subscribed to, though I do not believe that this
         * description is reflected to the user in the UI
         */
        $subscribe = false;
        $this->decoratedSubscribes = $this->topic->subscribesToBuild($build);
        if ($this->decoratedSubscribes) {
            $subscriberLabels = $this->subscriber->getLabels();
            $topicLabels = $this->topic->getLabelsFromBuild($build);
            // TODO: refactor collection to accept an array as the argument to has
            // TODO: refactor collection to accept its object type as an argument to has
            foreach ($subscriberLabels as $label) {
                if ($topicLabels->has($label->Text)) {
                    $subscribe = true;
                    break;
                }
            }
        }
        return $subscribe;
    }

    /**
     * @param Build $build
     * @return Topic|void
     */
    public function setTopicData(Build $build)
    {
        $labels = $this->subscriber->getLabels();
        $this->topic->setTopicDataWithLabels($build, $labels);
    }

    /**
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // TODO: Implement itemHasTopicSubject() method.
    }
}
