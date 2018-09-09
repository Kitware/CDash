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

class DynamicAnalysisDecorator extends Decorator
{

    private $template = "{{ name }} ({{ url }})\n";

    /**
     * @param Topic $topic
     * @return string|void
     */
    public function setTopic(Topic $topic)
    {
        $analyses = $topic->getTopicCollection();
        $counter = 0;
        $data = [];

        foreach ($analyses as $analysis) {
            $data[] = [
                'name' => $analysis->Name,
                'url' => $analysis->GetUrlForSelf(),
            ];
            if (++$counter === $this->maxTopicItems) {
                break;
            }
        }

        $maxReachedText = $this->maxTopicItems < $analyses->count() ?
            " (first {$this->maxTopicItems} included)" : '';
        $description = $topic->getTopicDescription();
        $full_text = $this->decorateWith($this->template, $data);
        $this->text = "\n*{$description}{$maxReachedText}*\n{$full_text}";
    }
}
