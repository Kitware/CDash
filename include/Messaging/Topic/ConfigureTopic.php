<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Collection\ConfigureCollection;

class ConfigureTopic extends Topic implements DecoratableInterface
{
    private $collection;
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $parentTopic = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $conf = $build->GetBuildConfigure();
        $subscribe = $parentTopic && ($conf->NumberOfErrors || $conf->NumberOfWarnings);
        return $subscribe;
    }

    public function setTopicData(Build $build)
    {
        $collection = $this->getTopicCollection();
        $configure = $build->GetBuildConfigure();
        $key = Topic::CONFIGURE; // no need to set multiple configures, they're all the same
        $collection->addItem($configure, $key);
    }

    public function getTopicName()
    {
        return self::CONFIGURE;
    }

    public function getTopicCount()
    {
        $collection = $this->getTopicCollection();
        $configure = $collection->get(Topic::CONFIGURE);
        return $configure->Status;
    }

    public function getTopicDescription()
    {
        return 'Configure Errors';
    }

    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = new ConfigureCollection();
        }
        return $this->collection;
    }

    /**
     * @param Build $build
     * @param $item
     * @return bool|void
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
    }
}
