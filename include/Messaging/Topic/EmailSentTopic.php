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

use CDash\Model\Build;

class EmailSentTopic extends Topic
{
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $subscribe = $this->topic->subscribesToBuild($build)
            && !$this->hasSubscriberAlreadyBeenNotified($build);
        return $subscribe;
    }

    public function getTopicName()
    {
        return $this->topic->getTopicName();
    }

    /**
     * @param Build $build
     * @param $item
     * @return bool
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // What is this? We've already determined that the build email was not sent
        // so just return true here
        return true;
    }
}
