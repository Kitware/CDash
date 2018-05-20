<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;
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
        $subscribe = $parentTopic && ($conf->NumberOfErrors > 0 || $conf->NumberOfWarnings > 0);
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
        // TODO: why is this referencing status?
        // return $configure->Status;
        // I think this should be:
        // return $configure->Status == 0 ? 0 : 1;
        // Apparently $configure->Status is correct, for the time being anyhow.
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
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
      // TODO: Implement itemHasTopicSubject() method.
    }
}
