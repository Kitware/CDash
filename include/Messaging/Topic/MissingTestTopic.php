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
use CDash\Model\BuildTest;
use CDash\Model\Label;
use CDash\Model\Test;

class MissingTestTopic extends  TestFailureTopic
{
    use IssueTemplateTrait;

    /**
     * This method queries the build to check for missing tests
     *
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $tests = $build->GetTestCollection();
        return $tests->count() && $build->GetNumberOfMissingTests() > 0;
    }

    /**
     * This method sets a build's missing tests in a TestCollection
     *
     * @param Build $build
     * @return void
     */
    public function setTopicData(Build $build)
    {
        $collection = $this->getTopicCollection();
        // GetMissingTests currently returns array
        $rows = $build->GetMissingTests();
        foreach ($rows as $id => $name) {
            $test = new Test();
            $test->Id = $id;
            $test->Name = $name;
            $buildTest = new BuildTest();
            $buildTest->BuildId = $build->Id;
            $test->SetBuildTest($buildTest);
            $collection->add($test);
        }
    }

    /**
     * This method will determine which of a Build's tests meet the criteria for adding to this
     * topic's TestCollection.
     *
     * @param Build $build
     * @param Test $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // not implemented
    }

    /**
     * @return string
     */
    public function getTopicDescription()
    {
        return 'Missing Tests';
    }

    public function getTopicName()
    {
        return 'MissingTest';
    }

    /**
     * @return bool
     */
    public function hasFixes()
    {
        // not implemented
        // return $this->diff && $this->diff['TestFailure']['notrun']['fixed'] > 0;
        return false;
    }

    /**
     * @return array
     */
    public function getFixes()
    {
        return [];
    }

    /**
     * @param Build $build
     * @return LabelCollection
     */
    public function getLabelsFromBuild(Build $build)
    {
        $tests = $build->GetTestCollection();
        $collection = new LabelCollection();
        /** @var Test $test */
        foreach ($tests as $test) {
            // No need to bother with passed tests
            if ($test->GetStatus() === Test::NOTRUN) {
                /** @var Label $label */
                foreach($test->GetLabelCollection() as $label) {
                    $collection->add($label);
                }
            }
        }
        return $collection;
    }
}
