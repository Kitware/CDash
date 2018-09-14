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

class UpdateTopic extends Topic implements DecoratableInterface
{
    /**
     * Subscribes to build if:
     *   a) This is an Update submission AND
     *   b) Has new errors/failures/warnings OR
     *   c) The update contains fixes and the user is an author listed in the update file
     * (note: difficult to determine which author fixed what)
     *
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        // TODO: this makes a call to the database every time, refactor
        $diff = $build->GetErrorDifferences();
        $subscribe = ! is_null($build->GetUpdate())
                  && $this->hasNewActionables($diff)
                  || $this->hasFixesForAuthor($build, $diff);

        return $subscribe;
    }

    /**
     * @param array $diff
     * @return bool
     */
    public function hasNewActionables(array $diff)
    {
        return $diff['buildwarningspositive'] > 0
            || $diff['builderrorspositive'] > 0
            || $diff['configurewarnings'] > 0
            || $diff['configureerrors'] > 0
            || $diff['testfailedpositive'] > 0
            || $diff['testnotrunpositive'] > 0;
    }

    /**
     * @param Build $build
     * @param array $diff
     * @return bool
     */
    public function hasFixesForAuthor(Build $build, array $diff)
    {
        $credentials = $this->getAuthorCredentials();
        $authors = $build->GetCommitAuthors();
        $isInUpdate = false;
        foreach ($credentials as $credential) {
            if (in_array($credential, $authors)) {
                $isInUpdate = true;
                break;
            }
        }

        return $isInUpdate
            && $diff['buildwarningsnegative']  > 0
            || $diff['testfailednegative']     > 0
            || $diff['testnotrunnegative']     > 0
            || $diff['builderrorsnegative']    > 0
            || $diff['configurewarnings']      < 0
            || $diff['configureerrors']        < 0;
    }

    /**
     * @return array
     */
    public function getAuthorCredentials()
    {
        // TODO: this requires a call to the database to retrieve what may be credentials
        //       not necessarily the same as the user's email address at CDash
        $author = $this->subscriber->getAddress();
        $credentials = [$author];

        // ...
        return $credentials;
    }

    /**
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        return true;
    }
}
