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

/**
 * Interface SaxInterface
 * @package CDash\Lib\Parser
 */
interface SaxInterface
{
    /**
     * @param $parser
     * @param $name
     * @param $attributes
     * @return mixed
     */
    public function startElement($parser, $name, $attributes);

    /**
     * @param $parser
     * @param $name
     * @return mixed
     */
    public function endElement($parser, $name);

    /**
     * @param $parser
     * @param $data
     * @return mixed
     */
    public function text($parser, $data);

    /**
     * @param $parser
     * @param $target
     * @param $data
     * @return mixed
     */
    public function processingInstruction($parser, $target, $data);

    /**
     * @param $parser
     * @param $open_entity_name
     * @param $base
     * @param $system_id
     * @param $public_id
     * @return mixed
     */
    public function externalEntity($parser, $open_entity_name, $base, $system_id, $public_id);

    /**
     * @param $parser
     * @param $open_entity_name
     * @param $base
     * @param $system_id
     * @param $public_id
     * @return mixed
     */
    public function skippedEntity($parser, $open_entity_name, $base, $system_id, $public_id);

    /**
     * @param $parser
     * @param $user_data
     * @param $prefix
     * @param $uri
     * @return mixed
     */
    public function startPrefixMapping($parser, $user_data, $prefix, $uri);

    /**
     * @param $parser
     * @param $user_data
     * @param $prefix
     * @return mixed
     */
    public function endPrefixMapping($parser, $user_data, $prefix);
}
