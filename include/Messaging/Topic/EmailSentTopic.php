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
use CDash\Model\BuildEmail;

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
            $type = $this->getTopicName();
            $category = ActionableTypes::$categories[$type];
            $collection = $build->GetBuildEmailCollection($category);
            $email = $this->subscriber->getAddress();
            $buildEmails = $collection->get($email);
            $subscribe = is_null($buildEmails);
        }
        return $subscribe;
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
