<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/


function filterdefinition_XML($key, $uitext, $type, $valuelist, $defaultvalue)
{
  $xml = '<def>';
  $xml .= add_XML_value('key', $key);
  $xml .= add_XML_value('uitext', $uitext);
  $xml .= add_XML_value('type', $type);
    // type == bool, enum, number, string, date
    // (enum not implemented yet...)
  $xml .= add_XML_value('valuelist', $valuelist);
  $xml .= add_XML_value('defaultvalue', $defaultvalue);
  $xml .= '</def>';

  return $xml;
}


function filterdefinitions_XML()
{
  $xml = '<filterdefinitions>';

  $xml .= filterdefinition_XML('buildduration', 'Build Duration', 'number', '', '0');
  $xml .= filterdefinition_XML('builderrors', 'Build Errors', 'number', '', '0');
  $xml .= filterdefinition_XML('buildwarnings', 'Build Warnings', 'number', '', '0');
  $xml .= filterdefinition_XML('buildname', 'Build Name', 'string', '', '');
  $xml .= filterdefinition_XML('buildstarttime', 'Build Time', 'date', '', '');
  $xml .= filterdefinition_XML('buildtype', 'Build Type', 'string', '', 'Nightly');
  $xml .= filterdefinition_XML('configureduration', 'Configure Duration', 'number', '', '0');
  $xml .= filterdefinition_XML('configureerrors', 'Configure Errors', 'number', '', '0');
  $xml .= filterdefinition_XML('configurewarnings', 'Configure Warnings', 'number', '', '0');
  $xml .= filterdefinition_XML('expected', 'Expected', 'bool', '', '');
  $xml .= filterdefinition_XML('groupname', 'Group', 'string', '', 'Nightly');
  $xml .= filterdefinition_XML('hasctestnotes', 'Has CTest Notes', 'bool', '', '');
  $xml .= filterdefinition_XML('hasusernotes', 'Has User Notes', 'bool', '', '');
  $xml .= filterdefinition_XML('label', 'Label', 'string', '', '');
  $xml .= filterdefinition_XML('site', 'Site', 'string', '', '');
  $xml .= filterdefinition_XML('buildgenerator', 'Submission Client', 'string', '', '2.6');
  $xml .= filterdefinition_XML('subproject', 'SubProject', 'string', '', '');
  $xml .= filterdefinition_XML('testsduration', 'Tests Duration', 'number', '', '', '0');
  $xml .= filterdefinition_XML('testsfailed', 'Tests Failed', 'number', '', '0');
  $xml .= filterdefinition_XML('testsnotrun', 'Tests Not Run', 'number', '', '0');
  $xml .= filterdefinition_XML('testspassed', 'Tests Passed', 'number', '', '0');
  $xml .= filterdefinition_XML('testtimestatus', 'Tests Timing Failed', 'number', '', '0');
  $xml .= filterdefinition_XML('updateduration', 'Update Duration', 'number', '', '0');
//  $xml .= filterdefinition_XML('updateerrors', 'Update Errors', 'number', '', '0');
//  $xml .= filterdefinition_XML('updatewarnings', 'Update Warnings', 'number', '', '0');
  $xml .= filterdefinition_XML('updatedfiles', 'Updated Files', 'number', '', '0');

  $xml .= '</filterdefinitions>';

  return $xml;
}


// Take a php $filterdata structure and return it as an XML string representation
//
function filterdata_XML($filterdata)
{
  $debug = $filterdata['debug']; // '0' or '1' -- shows debug info in HTML output
  $filtercombine = $filterdata['filtercombine']; // 'OR' or 'AND'
  $filters = $filterdata['filters']; // an array
  $showfilters = $filterdata['showfilters']; // 0 or 1

  $xml = '<filterdata>';
  $xml .= add_XML_value('debug', $debug);
  $xml .= add_XML_value('filtercombine', $filtercombine);
  $xml .= add_XML_value('script', $_SERVER['SCRIPT_NAME']);
  $xml .= add_XML_value('showfilters', $showfilters);

  $xml .= filterdefinitions_XML();

  $xml .= '<filters>';

  foreach ($filters as $filter)
  {
    $xml .= '<filter>';
    $xml .= add_XML_value('field', $filter['field']);
    $xml .= add_XML_value('compare', $filter['compare']);
    $xml .= add_XML_value('value', $filter['value']);
    $xml .= '</filter>';
  }

  $xml .= '</filters>';

  $xml .= '</filterdata>';

  return $xml;
}


// Analyze parameter values given in the URL _REQUEST and fill up a php $filterdata structure
// with them.
//
// http://arrakis.kitwarein.com/CDash/filters2.php?project=CMake&filtercount=1&showfilters=1&field1=buildname&compare1=65&value1=Linux
//
function get_filterdata_from_request()
{
  $echo_on = 0; // set to 1 for debugging only

  $sql = '';
  $xml = '';
  $clauses = 0;
  $filterdata = array();
  $filters = array();
  $add_filter = 0;
  $remove_filter = 0;
  $filterdata['hasdateclause'] = 0;

  @$filtercount = $_REQUEST['filtercount'];
  @$showfilters = $_REQUEST['showfilters'];

  @$clear = $_REQUEST['clear'];
  if ($clear == 'Clear')
  {
    if ($echo_on == 1)
    {
      echo 'info: Clear clicked... forcing $filtercount to 0<br/>';
    }

    $filtercount = 0;
  }
  else
  {
    if ($echo_on == 1)
    {
      echo 'info: using $filtercount=' . $filtercount . '<br/>';
    }
  }

  @$filtercombine = $_REQUEST['filtercombine'];
  if (strtolower($filtercombine) == 'or')
  {
    $sql_combine = 'OR';
  }
  else
  {
    $sql_combine = 'AND';
  }

  $sql = 'AND (';

  $offset=0;

  for ($i = 1; $i <= $filtercount+$offset; ++$i)
  {
    if(empty($_REQUEST['field'.$i]))
      {
      $offset++;
      continue;
      }
    $fieldinfo = $_REQUEST['field'.$i];
    $compare = $_REQUEST['compare'.$i];
    $value = $_REQUEST['value'.$i];
    @$add = $_REQUEST['add'.$i];
    @$remove = $_REQUEST['remove'.$i];

    $fieldinfo = split('/', $fieldinfo, 2);
    $field = $fieldinfo[0];
    $fieldtype = $fieldinfo[1];

    if ($add == '+')
    {
      //echo 'add after filter ' . $i . '<br/>';
      $add_filter = $i;
    }
    else if ($remove == '-')
    {
      //echo 'remove filter ' . $i . '<br/>';
      $remove_filter = $i;
    }


    $sql_field = '';
    $sql_compare = '';
    $sql_value = '';


    // Translate "comparison operation" and "compare-to
    // value" to SQL equivalents:
    //
    switch ($compare)
    {
      case 0:
        // bool do not compare
        // explicitly skip adding a clause when $compare == 0 ( "--" in the GUI )
      break;

      case 1:
      {
        // bool is true
        $sql_compare = '!=';
        $sql_value = "0";
      }
      break;

      case 2:
      {
        // bool is false
        $sql_compare = '=';
        $sql_value = "0";
      }
      break;

      case 40:
        // number do not compare
        // explicitly skip adding a clause when $compare == 40 ( "--" in the GUI )
      break;

      case 41:
      {
        // number is equal
        $sql_compare = '=';
        $sql_value = "'$value'";
      }
      break;

      case 42:
      {
        // number is not equal
        $sql_compare = '!=';
        $sql_value = "'$value'";
      }
      break;

      case 43:
      {
        // number is greater than
        $sql_compare = '>';
        $sql_value = "'$value'";
      }
      break;

      case 44:
      {
        // number is less than
        $sql_compare = '<';
        $sql_value = "'$value'";
      }
      break;

      case 60:
        // string do not compare
        // explicitly skip adding a clause when $compare == 60 ( "--" in the GUI )
      break;

      case 61:
      {
        // string is equal
        $sql_compare = '=';
        $sql_value = "'$value'";
      }
      break;

      case 62:
      {
        // string is not equal
        $sql_compare = '!=';
        $sql_value = "'$value'";
      }
      break;

      case 63:
      {
        // string contains
        $sql_compare = 'LIKE';
        $sql_value = "'%$value%'";
      }
      break;

      case 64:
      {
        // string does not contain
        $sql_compare = 'NOT LIKE';
        $sql_value = "'%$value%'";
      }
      break;

      case 65:
      {
        // string starts with
        $sql_compare = 'LIKE';
        $sql_value = "'$value%'";
      }
      break;

      case 66:
      {
        // string ends with
        $sql_compare = 'LIKE';
        $sql_value = "'%$value'";
      }
      break;

      case 80:
        // date do not compare
        // explicitly skip adding a clause when $compare == 80 ( "--" in the GUI )
      break;

      case 81:
      {
        // date is equal
        $sql_compare = '=';
        $sql_value = "'$value'";
      }
      break;

      case 82:
      {
        // date is not equal
        $sql_compare = '!=';
        $sql_value = "'$value'";
      }
      break;

      case 83:
      {
        // date is after
        $sql_compare = '>';
        $sql_value = "'$value'";
      }
      break;

      case 84:
      {
        // date is before
        $sql_compare = '<';
        $sql_value = "'$value'";
      }
      break;

      default:
        // warn php caller : unknown $compare value...

        add_log(
          'warning: unknown $compare value: ' . $compare,
          'get_filterdata_from_request');

        if ($echo_on == 1)
        {
          echo 'warning: unknown $compare value: ' . $compare . '<br/>';
        }
      break;
    }


//  SELECT b.id,b.siteid,b.name,b.type,b.generator,b.starttime,
//         b.endtime,b.submittime,g.name as groupname,gp.position,g.id as groupid
//         FROM build AS b, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp


    // Translate field name to SQL field name:
    //
    switch (strtolower($field))
    {
      case 'buildduration':
      {
        $sql_field = "ROUND(TIMESTAMPDIFF(SECOND,b.starttime,b.endtime)/60.0,1)";
      }
      break;

      case 'builderrors':
      {
        $sql_field = "IF((SELECT COUNT(buildid) FROM builderror WHERE buildid=b.id AND type='0')>0, (SELECT COUNT(buildid) FROM builderror WHERE buildid=b.id AND type='0'), IF((SELECT COUNT(buildid) FROM buildfailure WHERE buildid=b.id AND type='0')>0, (SELECT COUNT(buildid) FROM buildfailure WHERE buildid=b.id AND type='0'), 0))";
      }
      break;

      case 'buildgenerator':
      {
        $sql_field = 'b.generator';
      }
      break;

      case 'buildname':
      {
        $sql_field = 'b.name';
      }
      break;

      case 'buildstarttime':
      {
        $sql_field = 'b.starttime';
        $filterdata['hasdateclause'] = 1;
      }
      break;

      case 'buildtype':
      {
        $sql_field = 'b.type';
      }
      break;

      case 'buildwarnings':
      {
        $sql_field = "IF((SELECT COUNT(buildid) FROM builderror WHERE buildid=b.id AND type='1')>0, (SELECT COUNT(buildid) FROM builderror WHERE buildid=b.id AND type='1'), IF((SELECT COUNT(buildid) FROM buildfailure WHERE buildid=b.id AND type='1')>0, (SELECT COUNT(buildid) FROM buildfailure WHERE buildid=b.id AND type='1'), 0))";
      }
      break;

      case 'configureduration':
      {
        $sql_field = "(SELECT ROUND(TIMESTAMPDIFF(SECOND,starttime,endtime)/60.0,1) FROM configure WHERE buildid=b.id)";
      }
      break;

      case 'configureerrors':
      {
        $sql_field = "(SELECT SUM(status) FROM configure WHERE buildid=b.id AND status!='0')";
      }
      break;

      case 'configurewarnings':
      {
        $sql_field = "(SELECT COUNT(buildid) FROM configureerror WHERE buildid=b.id AND type='1')";
      }
      break;

      case 'expected':
      {
        $sql_field = "IF((SELECT COUNT(expected) FROM build2grouprule WHERE groupid=b2g.groupid AND buildtype=b.type AND buildname=b.name AND siteid=b.siteid)>0,(SELECT COUNT(expected) FROM build2grouprule WHERE groupid=b2g.groupid AND buildtype=b.type AND buildname=b.name AND siteid=b.siteid),0)";
      }
      break;

      case 'groupname':
      {
        $sql_field = 'g.name';
      }
      break;

      case 'hasctestnotes':
      {
        $sql_field = '(SELECT COUNT(*) FROM build2note WHERE buildid=b.id)';
      }
      break;

      case 'hasusernotes':
      {
        $sql_field = '(SELECT COUNT(*) FROM buildnote WHERE buildid=b.id)';
      }
      break;

      case 'label':
      {
        $sql_field = "(SELECT text FROM label, label2build WHERE label2build.labelid=label.id AND label2build.buildid=b.id)";
      }
      break;

      case 'site':
      {
        $sql_field = "(SELECT name FROM site WHERE site.id=b.siteid)";
      }
      break;

      case 'subproject':
      {
        $sql_field = "(SELECT name FROM subproject, subproject2build WHERE subproject2build.subprojectid=subproject.id AND subproject2build.buildid=b.id)";
      }
      break;

      case 'testsfailed':
      {
        $sql_field = "(SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id AND status='failed')";
      }
      break;

      case 'testsnotrun':
      {
        $sql_field = "(SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id AND status='notrun')";
      }
      break;

      case 'testspassed':
      {
        $sql_field = "(SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id AND status='passed')";
      }
      break;

      case 'testsduration':
      {
        $sql_field = "IF((SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id)>0,(SELECT ROUND(SUM(time)/60.0,1) FROM build2test WHERE buildid=b.id),0)";
      }
      break;

      case 'testtimestatus':
      {
        $sql_field = "(SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id AND timestatus>=(SELECT testtimemaxstatus FROM project WHERE projectid=b.projectid))";
      }
      break;

      case 'updatedfiles':
      {
        $sql_field = "(SELECT COUNT(buildid) FROM updatefile WHERE buildid=b.id)";
      }
      break;

      case 'updateduration':
      {
        $sql_field = "(SELECT ROUND(TIMESTAMPDIFF(SECOND,starttime,endtime)/60.0,1) FROM buildupdate WHERE buildid=b.id)";
      }
      break;

      case 'updateerrors':
      {
      // this one is pretty complicated... save it for later...
      //  $sql_field = "(SELECT COUNT(buildid) FROM buildupdate WHERE buildid=b.id)";
        add_log(
          'warning: updateerrors field not implemented yet...',
          'get_filterdata_from_request');
      }
      break;

      case 'updatewarnings':
      {
      // this one is pretty complicated... save it for later...
      //  $sql_field = "(SELECT COUNT(buildid) FROM buildupdate WHERE buildid=b.id)";
        add_log(
          'warning: updatewarnings field not implemented yet...',
          'get_filterdata_from_request');
      }
      break;

      default:
        // warn php caller : unknown $field value...

        add_log(
          'warning: unknown $field value: ' . $field,
          'get_filterdata_from_request');

        if ($echo_on == 1)
        {
          echo 'warning: unknown $field value: ' . $field . '<br/>';
        }
      break;
    }


    if ($sql_field != '' && $sql_compare != '')
    {
      if ($clauses > 0)
      {
        $sql .= ' ' . $sql_combine . ' ';
      }

      $sql .= $sql_field . ' ' . $sql_compare . ' ' . $sql_value;

      ++$clauses;
    }

    $filters[] = array(
      'field' => $field,
      'compare' => $compare,
      'value' => $value,
    );
  }


  if ($clauses == 0)
  {
    $sql = '';
  }
  else
  {
    $sql .= ')';
  }

  if ($echo_on == 1)
  {
    echo 'filter sql: ' . $sql . '<br/>';
    echo '<br/>';
  }

  // If no filters were passed in as parameters,
  // then add one default filter so that the user sees
  // somewhere to enter filter queries in the GUI:
  //
  if (0 == count($filters))
  {
    $filters[] = array(
      'field' => 'site',
      'compare' => 63,
      'value' => '',
    );
  }

  // If adding or removing a filter, do it before saving the
  // $filters array in the $filterdata...
  //
  if ($add_filter != 0)
  {
    //echo 'adding filter after '.$add_filter.'<br/>';

    $idx = $add_filter-1;

    // Add a copy of the existing filter we are adding after:
    //
    $filter = $filters[$idx];

    array_splice($filters, $idx, 0, array($filter));
      //
      // with $length=0, array_splice is an "insert array element" call...
  }

  if ($remove_filter != 0)
  {
    //echo 'removing filter '.$remove_filter.'<br/>';

    $idx = $remove_filter-1;

    array_splice($filters, $idx, 1);
      //
      // with $length=1, and no $replacement, array_splice is a "delete array element" call...
  }

  // Fill up filterdata and return it:
  //
  @$debug = $_REQUEST['debug'];
  if ($debug)
    {
    $filterdata['debug'] = 1; // '0' or '1' -- shows debug info in HTML output
    }
  else
    {
    $filterdata['debug'] = 0;
    }

  $filterdata['filtercombine'] = $filtercombine;
  $filterdata['filters'] = $filters;

  if ($showfilters)
    {
    $filterdata['showfilters'] = 1;
    }
  else
    {
    $filterdata['showfilters'] = 0;
    }

  $filterdata['sql'] = $sql;

  $xml = filterdata_XML($filterdata);

  $filterdata['xml'] = $xml;

  return $filterdata;
}


?>
