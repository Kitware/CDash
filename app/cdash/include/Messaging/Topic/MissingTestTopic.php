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

use App\Models\BuildTest;
use App\Models\Test;

use CDash\Collection\TestCollection;
use CDash\Model\Build;

class MissingTestTopic extends Topic
{
    use IssueTemplateTrait;

    /** @var TestCollection $collection */
    private $collection;

    /**
     * This method queries the build to check for missing tests
     *
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        return $build->GetNumberOfMissingTests() > 0;
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
            $test->id = $id;
            $test->name = $name;
            $buildTest = new BuildTest();
            $buildTest->buildid = $build->Id;
            $buildTest->test = $test;
            $collection->add($buildTest);
        }
    }

    /**
     * @return TestCollection
     */
    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = new TestCollection();
        }
        return $this->collection;
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
     * @return string
     */
    public function getTopicDescription()
    {
        return 'Missing Tests';
    }

    public function getTopicName()
    {
        return Topic::TEST_MISSING;
    }
}
