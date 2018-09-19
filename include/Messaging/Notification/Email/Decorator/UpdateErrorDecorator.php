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

namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;

class UpdateErrorDecorator extends Decorator
{
    private $template = "*{{ description }}*\nStatus: {{ status }} ({{ uri }})\n";

    /**
     * @param Topic $topic
     * @return string
     */
    public function setTopic(Topic $topic)
    {
        $collection = $topic->getBuildCollection();
        /** @var Build $build */
        $build = $collection->current();
        $update = $build->GetBuildUpdate();

        $data = [
            'description' => $topic->getTopicDescription(),
            'status' => $update->Status,
            'uri' => $update->GetUrlForSelf(),
        ];

        $this->text = $this->decorateWith($this->template, $data);
        return $this->text;
    }
}
