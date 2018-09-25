<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\LabelCollection;
use CDash\Model\Build;
use CDash\Collection\ConfigureCollection;
use CDash\Model\BuildConfigure;

class ConfigureTopic extends Topic implements Decoratable, Labelable
{
    private $collection;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $conf = $build->GetBuildConfigure();
        $subscribe = $conf->NumberOfErrors > 0;
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
        $configure = $collection->current();
        if (is_a($configure, BuildConfigure::class)) {
            return (int) $configure->NumberOfErrors;
        }
        return 0;
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

    /**
     * @param Build $build
     * @return LabelCollection
     */
    public function getLabelsFromBuild(Build $build)
    {
        // TODO: refactor, allow multiple collections to be merged with one another
        $configure = $build->GetBuildConfigure();
        $collection = new LabelCollection();
        $labels = $configure->GetLabelCollection();
        foreach ($labels as $lbl) {
            $collection->add($lbl);
        }

        $labels = $build->GetLabelCollection();
        foreach ($labels as $lbl) {
            $collection->add($lbl);
        }
        return $collection;
    }

    /**
     * @param Build $build
     * @param LabelCollection $labels
     * @return void
     */
    public function setTopicDataWithLabels(Build $build, LabelCollection $labels)
    {
       $collection = $this->getTopicCollection();
       $collection->add($build->GetBuildConfigure());
    }
}
