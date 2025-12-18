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

use CDash\Collection\DynamicAnalysisCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Model\Build;
use CDash\Model\DynamicAnalysis;
use CDash\Model\SubscriberInterface;

class DynamicAnalysisTopic extends Topic implements Decoratable
{
    use IssueTemplateTrait;

    protected static $statuses = [DynamicAnalysis::NOTRUN, DynamicAnalysis::FAILED];

    /** @var DynamicAnalysisCollection */
    private $collection;

    public function subscribesToBuild(Build $build): bool
    {
        $ancestorSubscribe = null === $this->topic ? true : $this->topic->subscribesToBuild($build);
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

    public function getTopicCount(): int
    {
        return $this->collection->count();
    }

    public function itemHasTopicSubject(Build $build, $item): bool
    {
        return in_array($item->Status, self::$statuses);
    }

    public function getTopicCollection(): DynamicAnalysisCollection
    {
        if (!$this->collection) {
            $this->collection = new DynamicAnalysisCollection();
        }
        return $this->collection;
    }

    public function getTopicName(): string
    {
        return Topic::DYNAMIC_ANALYSIS;
    }

    public function getTopicDescription(): string
    {
        return 'Dynamic analysis tests failing or not run';
    }

    public function isSubscribedToBy(SubscriberInterface $subscriber): bool
    {
        $subscribes = false;
        $preferences = $subscriber->getNotificationPreferences();

        if ($preferences->get(NotifyOn::DYNAMIC_ANALYSIS)) {
            $subscribes = true;
        }

        return $subscribes;
    }
}
