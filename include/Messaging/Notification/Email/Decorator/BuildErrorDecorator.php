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
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;

class BuildErrorDecorator extends Decorator
{
    private $srcTemplate = "{{ srcfile }} line {{ srcline }} ({{ uri }})\n{{ text }}\n{{ context }}\n";
    private $txtTemplate = "{{ text }}\n{{ context }}\n";

    /**
     * @param Topic $topic
     * @return string
     */
    public function setTopic(Topic $topic)
    {
        $errors = $topic->getTopicCollection();
        $counter = 0;
        $data = [];

        /** @var BuildError $error */
        foreach ($errors as $error) {
            $line = [
                'text' => $this->getErrorText($error),
                'context' => $this->getErrorPostContext($error),
            ];
            $tmpl = $this->txtTemplate;

            if (strlen($error->SourceFile) > 0) {
                $line = array_merge($line, [
                    'srcfile' => $error->SourceFile,
                    'srcline' => $this->getErrorSourceLine($error),
                    'uri' => $error->GetUrlForSelf(),
                ]);
                $tmpl = $this->srcTemplate;
            }
            $data[] = $line;
            if (++$counter === $this->maxTopicItems) {
                break;
            }
        }
        $description = $topic->getTopicDescription();
        $maxReachedText = $this->maxTopicItems < $errors->count() ?
            " (first {$this->maxTopicItems} included)" : '';
        $this->text = "\n*{$description}*{$maxReachedText}\n{$this->decorateWith($tmpl, $data)}\n";
        return $this->text;
    }

    protected function getErrorText($error)
    {
        return $this->isAFailure($error) ? $error->StdError : $error->Text;
    }

    protected function getErrorPostContext($error)
    {
        return $this->isAFailure($error) ? '' : $error->PostContext;
    }

    protected function getErrorSourceLine($error)
    {
        return $this->isAFailure($error) ? null : $error->SourceLine;
    }

    protected function isAFailure($error)
    {
        return is_a($error, BuildFailure::class);
    }
}
