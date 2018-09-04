<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Messaging\Topic;

use CDash\Collection\BuildFailureCollection;
use CDash\Model\Build;

/**
 * Class BuildFailureTopic
 * @package CDash\Messaging\Topic
 */
class BuildFailureTopic extends Topic implements DecoratableInterface
{
    /** @var BuildFailureCollection $collection */
    private $collection;

    /** @var int $type */
    private $type;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $ancestorSubscribes = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        // TODO: if $this->type has not yet been set, this will pass a null value, desired?
        $subscribe = $ancestorSubscribes && $build->GetBuildFailureCount($this->type) > 0;
        return $subscribe;
    }

    /**
     * @param Build $build
     * @return Topic|void
     */
    public function setTopicData(Build $build)
    {
        $collection = $this->getTopicCollection();
        foreach ($build->Failures as $fail) {
            if ($this->itemHasTopicSubject($build, $fail)) {
                $collection->add($fail);
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
     * @return bool
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        return $item->Type === $this->type;
    }

    /**
     * @return BuildFailureCollection|\CDash\Collection\CollectionInterface
     */
    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = new BuildFailureCollection();
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

    /**
     * @return string
     */
    public function getTopicName()
    {
        if (Build::TYPE_ERROR === $this->type) {
            return 'BuildFailureError';
        }

        if (Build::TYPE_WARN === $this->type) {
            return 'BuildFailureWarning';
        }
    }

    /**
     * @return string
     */
    public function getTopicDescription()
    {
        if (Build::TYPE_ERROR === $this->type) {
            return 'Errors';
        }

        if (Build::TYPE_WARN === $this->type) {
            return 'Warnings';
        }
    }
}
