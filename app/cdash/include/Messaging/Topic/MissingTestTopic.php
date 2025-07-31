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
use CDash\Model\Build;

class MissingTestTopic extends Topic
{
    use IssueTemplateTrait;

    /** @var Illuminate\Support\Collection */
    private $collection;

    /**
     * This method queries the build to check for missing tests
     */
    public function subscribesToBuild(Build $build): bool
    {
        return $build->GetNumberOfMissingTests() > 0;
    }

    /**
     * This method sets a build's missing tests in a TestCollection
     *
     * @return void
     */
    public function setTopicData(Build $build)
    {
        $collection = $this->getTopicCollection();
        // GetMissingTests currently returns array
        $rows = $build->GetMissingTests();
        foreach ($rows as $id => $name) {
            $buildTest = new Test();
            $buildTest->buildid = $build->Id;
            $buildTest->testname = $name;
            $collection->put($name, $buildTest);
        }
    }

    /**
     * @return Illuminate\Support\Collection
     */
    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = collect();
        }
        return $this->collection;
    }

    public function getTopicCount(): int
    {
        $collection = $this->getTopicCollection();
        return $collection->count();
    }

    public function getTopicDescription(): string
    {
        return 'Missing Tests';
    }

    public function getTopicName(): string
    {
        return Topic::TEST_MISSING;
    }
}
