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
namespace CDash\Lib\Collection;

interface CollectionInterface extends \Iterator, \Countable
{

    /**
     * @param $item
     * @param null $name
     * @return mixed
     */
    public function addItem($item, $name = null);

    /**
     * Returns true if the collection has items, and false if not.
     * @return boolean
     */
    public function hasItems();

    /**
     * @param $key
     * @return bool
     */
    public function has($key);

    /**
     * @param $key
     * @return mixed
     */
    public function get($key);

    /**
     * @param $key
     * @return mixed
     */
    public function remove($key);

    /**
     * @return array
     */
    public function toArray();
}
