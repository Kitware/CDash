<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Collection\TestCollection;
use CDash\Config;
use CDash\Messaging\DecoratorInterface;
use CDash\Messaging\Subscription\Subscription;

class TestFailureTopic extends Topic
{
    private $collection;

    public function __construct(TestCollection $collection)
    {
        $this->collection = $collection;
    }

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
        $subscribe = $build->GetTestFailedCount() > 0;
        if ($subscribe) {
            // TODO: refactor so that this is possible
            $tests = $build->GetTestCollection();
            $max_items = $build->GetProject()->EmailMaxItems;
            do {
                $test = $tests->current();
                if ($test->HasFailed()) {
                    $this->collection->add($test);
                }
                $tests->next();
            } while (--$max_items && $tests->valid());
        }
        return $subscribe;
    }
}
