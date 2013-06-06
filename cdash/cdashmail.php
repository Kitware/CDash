<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: cdashmail.php 3244 2012-03-01 15:58:36Z david.cole $
  Language:  PHP
  Date:      $Date: 2012-03-01 15:58:36 +0000 (Thu, 01 Mar 2012) $
  Version:   $Revision: 3244 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/


function cdashmail($to, $subject, $body, $headers)
{
  return mail("$to", "$subject", "$body", "$headers");
}


?>
