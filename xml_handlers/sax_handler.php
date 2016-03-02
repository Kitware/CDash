<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

interface SaxHandler
{
    public function startElement($parser, $name, $attributes);

    public function endElement($parser, $name);

    public function text($parser, $data);

    public function processingInstruction($parser, $target, $data);

    public function externalEntity($parser, $open_entity_name, $base, $system_id, $public_id);

    public function skippedEntity($parser, $open_entity_name, $base, $system_id, $public_id);

    public function startPrefixMapping($parser, $user_data, $prefix, $uri);

    public function endPrefixMapping($parser, $user_data, $prefix);
}
