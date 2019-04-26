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

use CDash\Messaging\Notification\NotifyOn;
use CDash\Model\Build;
use CDash\Model\SubscriberInterface;

class UpdateErrorTopic extends Topic implements Decoratable, Fixable
{
    use IssueTemplateTrait;

    private $diff;
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $this->diff = $build->GetDiffWithPreviousBuild();
        $buildUpdate = $build->GetBuildUpdate();
        return $buildUpdate && $buildUpdate->Status > 0;
    }

    /**
     * @param Build $build
     * @return $this
     */
    public function addBuild(Build $build)
    {
        $collection = $this->getBuildCollection();
        $collection->add($build);
        return $this;
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        return true;
    }

    public function getTopicName()
    {
        return Topic::UPDATE_ERROR;
    }

    public function getTopicDescription()
    {
        return 'Update Errors';
    }

    /**
     * @param SubscriberInterface $subscriber
     * @return bool
     */
    public function isSubscribedToBy(SubscriberInterface $subscriber)
    {
        $subscribes = false;
        $preferences = $subscriber->getNotificationPreferences();

        if ($preferences->get(NotifyOn::UPDATE_ERROR)
         || $preferences->get(NotifyOn::FIXED)) {
            $subscribes = true;
        }
        return $subscribes;
    }

    /**
     * @return bool
     */
    public function hasFixes()
    {
        $hasFixes = false;

        if ($this->diff) {
            $diff = $this->diff;
            if (isset($diff['BuildWarning']['fixed']) && $diff['BuildWarning']['fixed'] > 0) {
                $hasFixes = true;
            }

            if (isset($diff['BuildError']['fixed']) && $diff['BuildError']['fixed'] > 0) {
                $hasFixes = true;
            }

            if (isset($diff['TestFailure']['failed']['fixed']) && $diff['TestFailure']['failed']['fixed'] > 0) {
                $hasFixes = true;
            }

            if (isset($diff['TestFailure']['missing']['fixed']) && $diff['TestFailure']['missing']['fixed'] > 0) {
                $hasFixes = true;
            }
        }

        return $hasFixes;
    }

    /**
     * @return array
     */
    public function getFixes()
    {
        return $this->diff;
    }
}
