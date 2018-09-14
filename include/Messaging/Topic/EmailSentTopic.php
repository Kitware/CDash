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

use CDash\Model\ActionableTypes;
use CDash\Model\Build;

class EmailSentTopic extends Topic
{
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $parentTopic = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $subscribe = false;
        if ($parentTopic) {
            $subscribe =
              ! $this->hasPreviousBuild($build)     ||
                $this->hasPreviousBuildDiff($build) &&
              ! $this->isEmailForBuildSent($build);
        }
        return $subscribe;
    }

    public function hasPreviousBuild(Build $build)
    {
        $previous_build_id = $build->GetPreviousBuildId();
        return $previous_build_id > 0;
    }

    /**
     * @param Build $build
     * @return bool
     */
    public function hasPreviousBuildDiff(Build $build)
    {
        // TODO: do we want to only check the diff that is the subject of $this->topic?
        $diff = $build->GetErrorDifferences();
        return $diff['buildwarningspositive'] > 0
            || $diff['builderrorspositive'] > 0
            || $diff['configurewarnings'] > 0
            || $diff['configureerrors'] > 0
            || $diff['testfailedpositive'] > 0
            || $diff['testnotrunpositive'] > 0;
    }

    /**
     * @param Build $build
     * @return bool
     */
    public function isEmailForBuildSent(Build $build)
    {
        $type = $this->getTopicName();
        $category = ActionableTypes::$categories[$type];
        $collection = $build->GetBuildEmailCollection($category);
        $email = $this->subscriber->getAddress();
        return $collection->has($email);
    }

    /**
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // What is this? We've already determined that the build email was not sent
        // so just return true here
        return true;
    }
}
