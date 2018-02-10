<?php
namespace CDash\archive\archive\Messaging\Topic;

use ActionableBuildInterface;
use CDash\Messaging\Collection\TopicCollection;

class TopicFactory
{
    const RX_DOT_ENTRY = '/^\./';
    const RX_TOPIC_CLASS = '/^(\w+)Topic.php';

    public function createTopic(ActionableBuildInterface $build, TopicCollection $collection = null)
    {
        $topics = is_null($collection) ? $this->getTopicCollection() : $collection;
        $this->loadTopics($topics);
        $subscription = new Topic();
        foreach ($topics as $topic) {
            if ($topic->hasTopic($build)) {
                $subscription->addTopic($topic);
            }
        }
    }

    protected function loadTopics(TopicCollection $topics)
    {
        $cwd = dirname(__FILE__) . '/Topics';
        $dir = opendir($cwd);

        if ($dir) {
            $entry = readdir($dir);
            while ($entry) {
                // skip any dot files
                if (preg_match(self::RX_DOT_ENTRY, $entry)) {
                    $entry = readdir($dir);
                    continue;
                }

                if (preg_match(self::RX_TOPIC_CLASS, $entry, $match)) {
                    $class_name = $match[1];
                    if (!class_exists($class_name)) {
                        $class_path = "{$cwd}/{$entry}";
                        require_once $class_path;
                        $topic = new $class_name();
                        if (is_a($topic, TopicDiscoveryInterface::class)) {
                            $topics->add(new $class_name());
                        }
                    }
                }
            }
        }
    }

    private function getTopicCollection()
    {
        return new TopicCollection();
    }
}
