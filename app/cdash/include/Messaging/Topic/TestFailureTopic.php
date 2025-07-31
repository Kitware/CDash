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

use App\Models\Test;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Model\Build;
use CDash\Model\Label;
use CDash\Model\SubscriberInterface;
use Illuminate\Support\Collection;

/**
 * Class TestFailureTopic
 */
class TestFailureTopic extends Topic implements Decoratable, Fixable, Labelable
{
    use IssueTemplateTrait;

    /** @var Collection */
    protected $collection;

    /** @var array */
    protected $diff;

    /**
     * This method queries the build to check for failed tests
     */
    public function subscribesToBuild(Build $build): bool
    {
        if ($this->subscriber) {
            $preferences = $this->subscriber->getNotificationPreferences();
            if ($preferences->get(NotifyOn::REDUNDANT) && $build->GetNumberOfFailedTests() > 0) {
                return true;
            }
        }

        $subscribe = false;
        $this->diff = $build->GetDiffWithPreviousBuild();
        if ($this->diff) {
            $subscribe = $this->diff['TestFailure']['failed']['new'] > 0;
        }

        return $subscribe;
    }

    /**
     * This method sets a build's failed tests in a Collection
     *
     * @return void
     */
    public function setTopicData(Build $build)
    {
        $collection = $this->getTopicCollection();
        $buildtests = $build->GetTestCollection();
        foreach ($buildtests as $test_name => $buildtest) {
            if ($this->itemHasTopicSubject($build, $buildtest)) {
                $collection->put($test_name, $buildtest);
            }
        }
    }

    /**
     * This method returns the Collection containing a build's failed tests
     */
    public function getTopicCollection(): Collection
    {
        if (!$this->collection) {
            $this->collection = collect();
        }
        return $this->collection;
    }

    /**
     * This method returns the subject of the topic
     *
     * @return string
     *
     * TODO: is it possible to create a convention where this method can be abstracted to simply:
     *   return __CLASS__;
     */
    public function getTopicName(): string
    {
        return self::TEST_FAILURE;
    }

    public function getTopicDescription(): string
    {
        return 'Failing Tests';
    }

    public function getTopicCount(): int
    {
        return $this->getTopicCollection()->count();
    }

    /**
     * This method will determine which of a Build's tests meet the criteria for adding to this
     * topic's TestCollection.
     *
     * @param Test $item
     */
    public function itemHasTopicSubject(Build $build, $item): bool
    {
        return $item->status === Test::FAILED;
    }

    public function hasFixes(): bool
    {
        return $this->diff && $this->diff['TestFailure']['failed']['fixed'] > 0;
    }

    public function getFixes(): array
    {
        $fixed = [];
        if ($this->diff) {
            $fixed = $this->diff['TestFailure'];
        }
        return $fixed;
    }

    public function setTopicDataWithLabels(Build $build, Collection $labels): void
    {
        $collection = $this->getTopicCollection();
        $buildtests = $build->GetTestCollection();
        /** @var Test $buildtest */
        foreach ($buildtests as $test_name => $buildtest) {
            if ($this->itemHasTopicSubject($build, $buildtest)) {
                $testLabels = $buildtest->getLabels();
                foreach ($labels as $label) {
                    if ($testLabels->has($label->Text)) {
                        $collection->put($test_name, $buildtest);
                    }
                }
            }
        }
    }

    public function getLabelsFromBuild(Build $build): Collection
    {
        $buildtests = $build->GetTestCollection();
        $collection = collect();
        foreach ($buildtests as $test_name => $buildtest) {
            // No need to bother with passed buildtests
            if ($this->itemHasTopicSubject($build, $buildtest)) {
                /** @var Label $label */
                foreach ($buildtest->getLabels() as $label) {
                    $collection->put($label->Text, $label);
                }
            }
        }
        return $collection;
    }

    public function isSubscribedToBy(SubscriberInterface $subscriber): bool
    {
        $subscribes = false;
        $preferences = $subscriber->getNotificationPreferences();

        if ($preferences->get(NotifyOn::TEST_FAILURE)
         || $preferences->get(NotifyOn::FIXED)) {
            $subscribes = true;
        }

        return $subscribes;
    }
}
