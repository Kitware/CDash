<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\DynamicAnalysisCollection;
use CDash\Model\Build;
use CDash\Model\DynamicAnalysis;

class DynamicAnalysisTopic extends Topic implements DecoratableInterface
{
    protected static $statuses = [DynamicAnalysis::NOTRUN, DynamicAnalysis::FAILED];

    /** @var DynamicAnalysisCollection $collection */
    private $collection;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $ancestorSubscribe = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $collection = $build->GetDynamicAnalysisCollection();
        $subscribe = false;
        if ($ancestorSubscribe) {
            foreach ($collection as $analysis) {
                if (in_array($analysis->Status, self::$statuses)) {
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
        $collection = $build->GetDynamicAnalysisCollection();
        $analyses = $this->getTopicCollection();
        foreach ($collection as $analysis) {
            if ($this->itemHasTopicSubject($build, $analysis)) {
                $analyses->add($analysis);
            }
        }
    }

    /**
     * @return int
     */
    public function getTopicCount()
    {
        if ($this->collection) {
            return $this->collection->count();
        }
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        $criteria = $this->getTopicCallables();
        $hasTopicSubject = in_array($item->Status, self::$statuses);
        foreach ($criteria as $criterion) {
            $hasTopicSubject = $hasTopicSubject && $criterion($build, $item);
            if (!$hasTopicSubject) {
                break;
            }
        }
        return $hasTopicSubject;
    }

    /**
     * @return \CDash\Collection\CollectionInterface|DynamicAnalysisCollection
     */
    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = new DynamicAnalysisCollection();
        }
        return $this->collection;
    }

    /**
     * @return string
     */
    public function getTopicName()
    {
        return 'DynamicAnalysis';
    }

    /**
     * @return string
     */
    public function getTopicDescription()
    {
        return 'Dynamic analysis tests failing or not run';
    }
}
