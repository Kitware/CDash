<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Collection\TestCollection;
use CDash\Config;
use CDash\Messaging\DecoratorInterface;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Subscription\Subscription;

class TestFailureTopic extends Topic implements DecoratableInterface
{
    private $collection;

    // TODO: does not really belong here, consider decorator's responsibility
    public function getTopicDescription()
    {
        return 'Tests Failing';
    }

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $parentTopic = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $subscribe = $parentTopic && $build->GetTestFailedCount() > 0;

        return $subscribe;
    }

    public function setTopicData(Build $build)
    {
        $collection = $this->getTestCollection();
        $tests = $build->GetTestCollection();
        $max_items = $build->GetProject()->EmailMaxItems;
        do {
            $test = $tests->current();
            if ($test->HasFailed()) {
                $collection->add($test);
            }
            $tests->next();
        } while ($collection->count() <= $max_items && $tests->valid());
    }

    protected function getTestCollection()
    {
        if (!$this->collection) {
            $this->collection = new TestCollection();
        }
        return $this->collection;
    }

    public function getTopicName()
    {
        return self::TEST_FAILURE;
    }

    public function hasLabels(Build $build)
    {
        if (!$this->labels) {
            $this->labels = [];
        }

        $subscribed_labels = $this->subscriber->getLabels();
        foreach ($build->GetTestCollection() as $test) {
            foreach ($test->GetLabelCollection() as $label) {
                if (in_array($label->Text, $subscribed_labels)) {
                    $this->labels[] = $label->Text;
                }
            }
        }
        return (bool)(count($this->labels));
    }
}
