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

use CDash\Collection\ConfigureCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\SubscriberInterface;
use Illuminate\Support\Collection;

class ConfigureTopic extends Topic implements Decoratable, Labelable
{
    use IssueTemplateTrait;

    private $collection;

    public function subscribesToBuild(Build $build): bool
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

    public function getTopicName(): string
    {
        return self::CONFIGURE;
    }

    public function getTopicCount(): int
    {
        $collection = $this->getTopicCollection();
        $configure = $collection->current();
        $count = 0;
        if (is_a($configure, BuildConfigure::class)) {
            $count = (int) $configure->NumberOfErrors;
        }
        return $count;
    }

    public function getTopicDescription(): string
    {
        return 'Configure Errors';
    }

    public function getTopicCollection(): ConfigureCollection
    {
        if (!$this->collection) {
            $this->collection = new ConfigureCollection();
        }
        return $this->collection;
    }

    /**
     * @return bool
     *
     * TODO: this breaks interface segregation principle, refactor
     */
    public function itemHasTopicSubject(Build $build, $item): bool
    {
        return true;
    }

    public function getLabelsFromBuild(Build $build): Collection
    {
        // TODO: refactor, allow multiple collections to be merged with one another
        $configure = $build->GetBuildConfigure();
        $collection = collect();
        $labels = $configure->GetLabelCollection();
        foreach ($labels as $lbl) {
            $collection->put($lbl->Text, $lbl);
        }

        $labels = $build->GetLabelCollection();
        foreach ($labels as $lbl) {
            $collection->put($lbl->Text, $lbl);
        }
        return $collection;
    }

    public function setTopicDataWithLabels(Build $build, Collection $labels): void
    {
        $collection = $this->getTopicCollection();
        $collection->add($build->GetBuildConfigure());
    }

    public function isSubscribedToBy(SubscriberInterface $subscriber): bool
    {
        $subscribes = false;
        $preferences = $subscriber->getNotificationPreferences();

        if ($preferences->get(NotifyOn::CONFIGURE)) {
            $subscribes = true;
        }

        return $subscribes;
    }
}
