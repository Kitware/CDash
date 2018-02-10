<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Config;
use CDash\Messaging\Subscription\Subscription;

class TestFailureTopic extends Topic
{
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
        $subscribe = $build->GetNumberOfFailedTests() > 0;
        if ($subscribe) {
            $data = $build->GetFailedTests(Subscription::getMaxDisplayItems());
            $base_url = Config::getInstance()->getBaseUrl();
            foreach ($data as &$row) {
                // TODO: url should not be hardcoded this way, consider Route class
                $row['url'] = "{$base_url}/testDetails.php?test={$row['id']}&buildid={$build->Id}";
            }
            $this->setTopicData($data);
        }
        return $subscribe;
    }
}
