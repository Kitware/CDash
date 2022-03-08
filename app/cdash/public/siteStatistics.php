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

include_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Config;

$config = Config::getInstance();
$policy = checkUserPolicy(0);
if ($policy !== true) {
    return $policy;
}

include_once 'include/ctestparser.php';

$xml = begin_XML_for_XSLT();
$xml .= '<backurl>user.php</backurl>';
$xml .= '<title>CDash - Sites Statistics</title>';
$xml .= '<menutitle>CDash</menutitle>';
$xml .= '<menusubtitle>Site Statistics</menusubtitle>';

if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
    $query = pdo_query("SELECT siteid,sitename, SUM(elapsed) AS busytime FROM
      (
          SELECT site.id AS siteid,site.name AS sitename, project.name AS projectname, build.name AS buildname, build.type,
          AVG(submittime - buildupdate.starttime) AS elapsed
          FROM build, build2update, buildupdate, project, site
          WHERE
            submittime > NOW()- interval '168 hours'
            AND build2update.buildid = build.id
            AND buildupdate.id = build2update.updateid
            AND site.id = build.siteid
            AND build.projectid = project.id
            GROUP BY sitename,projectname,buildname,build.type,site.id
            ORDER BY elapsed DESC
          )
          AS summary
          GROUP BY sitename,summary.siteid
          ORDER BY busytime DESC
      ");
} else {
    $query = pdo_query('SELECT siteid,sitename, SEC_TO_TIME(SUM(elapsed)) AS busytime FROM
      (
      SELECT site.id AS siteid,site.name AS sitename, project.name AS projectname, build.name AS buildname, build.type,
      AVG(TIME_TO_SEC(TIMEDIFF(submittime, buildupdate.starttime))) AS elapsed
      FROM build, build2update, buildupdate, project, site
      WHERE
        submittime > TIMESTAMPADD(' . qiv('HOUR') . ', -168, NOW())
        AND build2update.buildid = build.id
        AND buildupdate.id = build2update.updateid
        AND site.id = build.siteid
        AND build.projectid = project.id
        GROUP BY sitename,projectname,buildname,build.type
        ORDER BY elapsed DESC
      )
      AS summary
      GROUP BY sitename
      ORDER BY busytime DESC
      ');
}
echo pdo_error();
while ($query_array = pdo_fetch_array($query)) {
    $xml .= '<site>';
    $xml .= add_XML_value('id', $query_array['siteid']);
    $xml .= add_XML_value('name', $query_array['sitename']);
    $xml .= add_XML_value('busytime', $query_array['busytime']);
    $xml .= '</site>';
}

$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'siteStatistics');
