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

use CDash\Collection\LabelCollection;
use CDash\Model\Build;
use CDash\Collection\ConfigureCollection;
use CDash\Model\BuildConfigure;

class ConfigureTopic extends Topic implements Decoratable, Labelable
{
    use IssueTemplateTrait;

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
        $count = 0;
        if (is_a($configure, BuildConfigure::class)) {
            $count = (int) $configure->NumberOfErrors;
        }
        return $count;
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
     *
     * TODO: this breaks interface segregation principle, refactor
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        return true;
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
