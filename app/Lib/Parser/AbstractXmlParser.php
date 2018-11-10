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

namespace CDash\Lib\Parser;

use CDash\Model\Build;
use CDash\Model\Site;

abstract class AbstractXmlParser implements SaxInterface, SubmissionParserInterface
{
    use SubmissionParser;

    /** @var Stack $stack */
    protected $stack;

    // TODO: refactor, remove
    // @see ctestparser.php ctest_parse (bottom) for refactor proposal
    /** @var string $backupFileName */
    public $backupFileName;

    /**
     * AbstractXmlParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        $this->projectId = $projectId;
        $this->stack = new Stack();
    }

    /**
     * @param StackInterface $stack
     * @return self
     */
    public function setStack(StackInterface $stack)
    {
        $this->stack = $stack;
        return $this;
    }

    /**
     * @return bool|mixed
     */
    protected function getParent()
    {
        return $this->stack->at($this->stack->size() - 2);
    }

    /**
     * @return mixed
     */
    protected function getElement()
    {
        return $this->stack->top();
    }

    /**
     * @param $parser
     * @param $name
     * @param $attributes
     * @return mixed
     */
    public function startElement($parser, $name, $attributes)
    {
        $this->stack->push($name);

        if ($name == 'SUBPROJECT') {
            $this->subProjectName = $attributes['NAME'];
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed
     */
    public function endElement($parser, $name)
    {
        $this->stack->pop();
    }

    /**
     * @param $parser
     * @param $target
     * @param $data
     * @return mixed
     */
    public function processingInstruction($parser, $target, $data)
    {
        // not implemented
    }

    /**
     * @param $parser
     * @param $open_entity_name
     * @param $base
     * @param $system_id
     * @param $public_id
     * @return mixed
     */
    public function externalEntity($parser, $open_entity_name, $base, $system_id, $public_id)
    {
        // not implemented
    }

    /**
     * @param $parser
     * @param $open_entity_name
     * @param $base
     * @param $system_id
     * @param $public_id
     * @return mixed
     */
    public function skippedEntity($parser, $open_entity_name, $base, $system_id, $public_id)
    {
        // not implemented
    }

    /**
     * @param $parser
     * @param $user_data
     * @param $prefix
     * @param $uri
     * @return mixed
     */
    public function startPrefixMapping($parser, $user_data, $prefix, $uri)
    {
        // not implemented
    }

    /**
     * @param $parser
     * @param $user_data
     * @param $prefix
     * @return mixed
     */
    public function endPrefixMapping($parser, $user_data, $prefix)
    {
        // not implemented
    }
}
