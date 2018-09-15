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
    private $template = "{{ text }}\n{{ error }}{{ context }}";

    /**
     * @param Topic $topic
     * @return string
     */
    public function setTopic(Topic $topic)
    {
        $errors = $topic->getTopicCollection();
        $counter = 0;
        $data = [];

        /** @var BuildError|BuildFailure $error */
        foreach ($errors as $error) {
            $line = [
                'context' => $this->decorateTemplateContext($error),
                'error' => $this->decorateTemplateError($error),
                'text' => $this->decorateTemplateText($error),
                'uri' => $error->GetUrlForSelf(),
            ];

            $data[] = $line;
            if (++$counter === $this->maxTopicItems) {
                break;
            }
        }
        $description = $topic->getTopicDescription();
        $maxReachedText = $this->maxTopicItems < $errors->count() ?
            " (first {$this->maxTopicItems} included)" : '';
        $this->text = "\n*{$description}*{$maxReachedText}\n{$this->decorateWith($this->template, $data)}\n";
        return $this->text;
    }

    /**
     * @param BuildError|BuildFailure $error
     * @return string
     */
    protected function decorateTemplateText($error)
    {
        $text = '';
        if (is_a($error, BuildError::class)) {
            $text = strlen($error->SourceFile) > 0 ?
                "{$error->SourceFile} line {$error->SourceLine} ({$error->GetUrlForSelf()})" :
                $error->Text;
        } elseif (is_a($error, BuildFailure::class)) {
            $text = "{$error->SourceFile} ({$error->GetUrlForSelf()})";
        }
        return $text;
    }


    /**
     * @param BuildError|BuildFailure $error
     * @return string
     */
    protected function decorateTemplateError($error)
    {
        $text = '';
        if (is_a($error, BuildError::class)) {
            // If the BuildError does not have a source file BuildError::Text
            // has already been set as the template text so use
            // BuildError::PostContext
            $text = strlen($error->SourceFile) > 0 ?
                trim($error->Text) :
                trim($error->PostContext);
        } elseif (is_a($error, BuildFailure::class)) {
            $text = $error->StdOutput;
        }

        $text .= strlen($text) ? PHP_EOL : '';
        return $text;
    }

    /**
     * @param BuildError|BuildFailure $error
     * @return string
     */
    protected function decorateTemplateContext($error)
    {
        $text = '';
        if(is_a($error, BuildError::class) && strlen($error->SourceFile) > 0) {
            $text = trim($error->PostContext);
        } elseif (is_a($error, BuildFailure::class)) {
            $text = trim($error->StdError);
        }

        $text .= strlen($text) ? PHP_EOL : '';
        return $text;
    }
}
