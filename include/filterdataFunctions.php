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

function getFilterDefinitionXML($key, $uitext, $type, $valuelist, $defaultvalue)
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

interface PageSpecificFilters
{
    public function getDefaultFilter();

    public function getDefaultShowLimit();

    public function getFilterDefinitionsXML();

    public function getSqlField($field);
}

class DefaultFilters implements PageSpecificFilters
{
    public function __construct()
    {
        // The way we concatenate text into a single value
        // depends on our database backend.
        global $CDASH_DB_TYPE;
        if ($CDASH_DB_TYPE === 'pgsql') {
            $this->TextConcat = "array_to_string(array_agg(text), ', ')";
        } else {
            $this->TextConcat = "GROUP_CONCAT(text SEPARATOR ', ')";
        }
    }

    public function getDefaultFilter()
    {
        trigger_error(
            'DefaultFilters::getDefaultFilter not implemented: subclass should override',
            E_USER_WARNING);
    }

    public function getDefaultShowLimit()
    {
        return true;
    }

    public function getFilterDefinitionsXML()
    {
        trigger_error(
            'DefaultFilters::getFilterDefinitionsXML not implemented: subclass should override',
            E_USER_WARNING);
    }

    public function getSqlField($field)
    {
        trigger_error(
            'DefaultFilters::getSqlField not implemented: subclass should override',
            E_USER_WARNING);
    }
}

class IndexPhpFilters extends DefaultFilters
{
    public function __construct()
    {
        parent::__construct();

        // Check if the SubProject filter was specified.
        // If so, we won't add SQL clauses for some other filters.
        // Instead we handle them in PHP code via build_survives_filter().
        $this->HasSubProjectsFilter = false;
        $filtercount = pdo_real_escape_numeric(@$_REQUEST['filtercount']);
        for ($i = 1; $i <= $filtercount; ++$i) {
            if (empty($_REQUEST['field' . $i])) {
                continue;
            }
            $field = htmlspecialchars(pdo_real_escape_string($_REQUEST['field' . $i]));
            if ($field === 'subprojects') {
                $this->HasSubProjectsFilter = true;
                break;
            }
        }
        $this->FiltersAffectedBySubProjects = array(
                'buildduration',
                'builderrors',
                'buildwarnings',
                'configureduration',
                'configureerrors',
                'configurewarnings',
                'testsduration',
                'testsfailed',
                'testsnotrun',
                'testspassed',
                'testtimestatus');
    }

    public function getDefaultFilter()
    {
        return array(
            'field' => 'site',
            'fieldtype' => 'string',
            'compare' => 63,
            'value' => '',
        );
    }

    public function getFilterDefinitionsXML()
    {
        $xml = '';

        $xml .= getFilterDefinitionXML('buildduration', 'Build Duration', 'number', '', '0');
        $xml .= getFilterDefinitionXML('builderrors', 'Build Errors', 'number', '', '0');
        $xml .= getFilterDefinitionXML('buildwarnings', 'Build Warnings', 'number', '', '0');
        $xml .= getFilterDefinitionXML('buildname', 'Build Name', 'string', '', '');
        $xml .= getFilterDefinitionXML('buildstamp', 'Build Stamp', 'string', '', '');
        $xml .= getFilterDefinitionXML('buildstarttime', 'Build Start Time', 'date', '', '');
        $xml .= getFilterDefinitionXML('buildtype', 'Build Type', 'string', '', 'Nightly');
        $xml .= getFilterDefinitionXML('configureduration', 'Configure Duration', 'number', '', '0');
        $xml .= getFilterDefinitionXML('configureerrors', 'Configure Errors', 'number', '', '0');
        $xml .= getFilterDefinitionXML('configurewarnings', 'Configure Warnings', 'number', '', '0');
        $xml .= getFilterDefinitionXML('expected', 'Expected', 'bool', '', '');
        $xml .= getFilterDefinitionXML('groupname', 'Group', 'string', '', 'Nightly');
        $xml .= getFilterDefinitionXML('hascoverage', 'Has Coverage', 'bool', '', '');
        $xml .= getFilterDefinitionXML('hasctestnotes', 'Has CTest Notes', 'bool', '', '');
        $xml .= getFilterDefinitionXML('hasdynamicanalysis', 'Has Dynamic Analysis', 'bool', '', '');
        $xml .= getFilterDefinitionXML('hasusernotes', 'Has User Notes', 'bool', '', '');
        $xml .= getFilterDefinitionXML('label', 'Label', 'string', '', '');
        $xml .= getFilterDefinitionXML('revision', 'Revision', 'string', '', '');
        $xml .= getFilterDefinitionXML('site', 'Site', 'string', '', '');
        $xml .= getFilterDefinitionXML('buildgenerator', 'Submission Client', 'string', '', '2.8');
        $xml .= getFilterDefinitionXML('subproject', 'SubProject', 'string', '', '');
        $xml .= getFilterDefinitionXML('testsduration', 'Tests Duration', 'number', '', '', '0');
        $xml .= getFilterDefinitionXML('testsfailed', 'Tests Failed', 'number', '', '0');
        $xml .= getFilterDefinitionXML('testsnotrun', 'Tests Not Run', 'number', '', '0');
        $xml .= getFilterDefinitionXML('testspassed', 'Tests Passed', 'number', '', '0');
        $xml .= getFilterDefinitionXML('testtimestatus', 'Tests Timing Failed', 'number', '', '0');
        $xml .= getFilterDefinitionXML('updateduration', 'Update Duration', 'number', '', '0');
        $xml .= getFilterDefinitionXML('updatedfiles', 'Updated Files', 'number', '', '0');
        return $xml;
    }

    public function getSqlField($field)
    {
        // Some filters can return inaccurate results when we are also
        // filtering by SubProjects.  In these cases we do not modify
        // the SQL query and instead handle the filtration in PHP code as a
        // subsequent step.
        if ($this->HasSubProjectsFilter &&
                in_array($field, $this->FiltersAffectedBySubProjects)) {
            return '';
        }

        global $CDASH_DB_TYPE;
        $sql_field = '';
        switch (strtolower($field)) {
            case 'buildduration': {
                $sql_field = 'b.buildduration';
            }
                break;

            case 'builderrors': {
                $sql_field = 'b.builderrors';
            }
                break;

            case 'buildgenerator': {
                $sql_field = 'b.generator';
            }
                break;

            case 'buildname': {
                $sql_field = 'b.name';
            }
                break;

            case 'buildstamp': {
                $sql_field = 'b.stamp';
            }
                break;

            case 'buildstarttime': {
                $sql_field = 'b.starttime';
            }
                break;

            case 'buildtype': {
                $sql_field = 'b.type';
            }
                break;

            case 'buildwarnings': {
                $sql_field = 'b.buildwarnings';
            }
                break;

            case 'configureduration': {
                $sql_field = 'b.configureduration';
            }
                break;

            case 'configureerrors': {
                $sql_field = "b.configureerrors";
            }
                break;

            case 'configurewarnings': {
                $sql_field = "b.configurewarnings";
            }
                break;

            case 'expected': {
                $sql_field = 'IF((SELECT COUNT(expected) FROM build2grouprule WHERE groupid=b2g.groupid AND buildtype=b.type AND buildname=b.name AND siteid=b.siteid)>0,(SELECT COUNT(expected) FROM build2grouprule WHERE groupid=b2g.groupid AND buildtype=b.type AND buildname=b.name AND siteid=b.siteid),0)';
            }
                break;

            case 'groupname': {
                $sql_field = 'g.name';
            }
                break;

            case 'hascoverage': {
                $sql_field = '(SELECT COUNT(*) FROM coveragesummary WHERE buildid=b.id)';
            }
                break;

            case 'hasctestnotes': {
                $sql_field = '(SELECT COUNT(*) FROM build2note WHERE buildid=b.id)';
            }
                break;

            case 'hasdynamicanalysis': {
                $sql_field = '(SELECT COUNT(*) FROM dynamicanalysis WHERE buildid=b.id)';
            }
                break;

            case 'hasusernotes': {
                $sql_field = '(SELECT COUNT(*) FROM buildnote WHERE buildid=b.id)';
            }
                break;

            case 'label': {
                $sql_field = "(SELECT $this->TextConcat FROM label, label2build WHERE label2build.labelid=label.id AND label2build.buildid=b.id)";

            }
                break;

            case 'revision':
                $sql_field = 'bu.revision';
                break;

            case 'site': {
                $sql_field = '(SELECT name FROM site WHERE site.id=b.siteid)';
            }
                break;

            case 'subproject': {
                $sql_field = '(SELECT name FROM subproject, subproject2build WHERE subproject2build.subprojectid=subproject.id AND subproject2build.buildid=b.id)';
            }
                break;

            case 'testsfailed': {
                $sql_field = "b.testfailed";
            }
                break;

            case 'testsnotrun': {
                $sql_field = "b.testnotrun";
            }
                break;

            case 'testspassed': {
                $sql_field = "b.testpassed";
            }
                break;

            case 'testsduration': {
                $sql_field = 'btt.time';
            }
                break;

            case 'testtimestatus': {
                $sql_field = 'b.testtimestatusfailed';
            }
                break;

            case 'updatedfiles': {
                $sql_field = '(SELECT COUNT(uf.updateid) FROM updatefile AS uf, build2update AS b2u WHERE b2u.updateid=uf.updateid AND b2u.buildid=b.id)';
            }
                break;

            case 'updateduration': {
                if ($CDASH_DB_TYPE === 'pgsql') {
                    $sql_field = 'ROUND(EXTRACT(EPOCH FROM (bu.endtime - bu.starttime))::numeric / 60, 1)';
                } else {
                    $sql_field = 'ROUND(TIMESTAMPDIFF(SECOND,bu.starttime,bu.endtime)/60.0,1)';
                }
            }
                break;

            case 'updateerrors': {
                // this one is pretty complicated... save it for later...
                //  $sql_field = "(SELECT COUNT(buildid) FROM buildupdate WHERE buildid=b.id)";
                add_log(
                    'warning: updateerrors field not implemented yet...',
                    'get_sql_field');
            }
                break;

            case 'updatewarnings': {
                // this one is pretty complicated... save it for later...
                //  $sql_field = "(SELECT COUNT(buildid) FROM buildupdate WHERE buildid=b.id)";
                add_log(
                    'warning: updatewarnings field not implemented yet...',
                    'get_sql_field');
            }
                break;

            case 'subprojects': {
                // Handle via custom logic rather than a modified SQL query.
                $sql_field = '';
            }
                break;

            default:
                trigger_error('unknown $field value: ' . $field, E_USER_WARNING);
                break;
        }
        return $sql_field;
    }
}

class QueryTestsPhpFilters extends DefaultFilters
{
    public function getDefaultFilter()
    {
        return array(
            'field' => 'testname',
            'fieldtype' => 'string',
            'compare' => 63,
            'value' => '',
        );
    }

    public function getFilterDefinitionsXML()
    {
        $xml = '';

        $xml .= getFilterDefinitionXML('buildname', 'Build Name', 'string', '', '');
        $xml .= getFilterDefinitionXML('buildstarttime', 'Build Start Time', 'date', '', '');
        $xml .= getFilterDefinitionXML('buildtype', 'Build Type', 'string', '', 'Nightly');
        $xml .= getFilterDefinitionXML('details', 'Details', 'string', '', '');
        $xml .= getFilterDefinitionXML('label', 'Label', 'string', '', '');
        $xml .= getFilterDefinitionXML('site', 'Site', 'string', '', '');
        $xml .= getFilterDefinitionXML('status', 'Status', 'string', '', '');
        $xml .= getFilterDefinitionXML('testname', 'Test Name', 'string', '', '');
        $xml .= getFilterDefinitionXML('time', 'Time', 'number', '', '');
        return $xml;
    }

    public function getSqlField($field)
    {
        $sql_field = '';
        switch (strtolower($field)) {
            case 'buildname': {
                $sql_field = 'b.name';
            }
                break;

            case 'buildstarttime': {
                $sql_field = 'b.starttime';
            }
                break;

            case 'buildtype': {
                $sql_field = 'b.type';
            }
                break;

            case 'details': {
                $sql_field = 'test.details';
            }
                break;

            case 'label': {
                $sql_field = "(SELECT $this->TextConcat FROM label, label2test WHERE label2test.testid=test.id AND label2test.labelid = label.id)";
            }
                break;

            case 'site': {
                $sql_field = 'site.name';
            }
                break;

            case 'status': {
                $sql_field = 'build2test.status';
            }
                break;

            case 'testname': {
                $sql_field = 'test.name';
            }
                break;

            case 'time': {
                $sql_field = 'build2test.time';
            }
                break;

            default:
                trigger_error('unknown $field value: ' . $field, E_USER_WARNING);
                break;
        }
        return $sql_field;
    }
}

class ViewCoveragePhpFilters extends DefaultFilters
{
    public function getDefaultFilter()
    {
        return array(
            'field' => 'filename',
            'fieldtype' => 'string',
            'compare' => 63,
            'value' => '',
        );
    }

    public function getDefaultShowLimit()
    {
        // Do not show the limit field on this page, since the data table
        // has paging capabilities
        return false;
    }

    public function getFilterDefinitionsXML()
    {
        $xml = '';

        $xml .= getFilterDefinitionXML('coveredlines', 'Covered Lines', 'number', '', '0');
        $xml .= getFilterDefinitionXML('filename', 'Filename', 'string', '', '');
        $xml .= getFilterDefinitionXML('labels', 'Labels', 'string', '', '');
        //$xml .= getFilterDefinitionXML('percentage', 'Percentage', 'number', '', '0');
        $xml .= getFilterDefinitionXML('priority', 'Priority', 'string', '', '');
        //$xml .= getFilterDefinitionXML('status', 'Status', 'string', '', '');
        $xml .= getFilterDefinitionXML('totallines', 'Total Lines', 'number', '', '0');
        $xml .= getFilterDefinitionXML('uncoveredlines', 'Uncovered Lines', 'number', '', '0');
        return $xml;
    }

    public function getSqlField($field)
    {
        $sql_field = '';
        switch (strtolower($field)) {
            case 'coveredlines': {
                $sql_field = 'c.loctested';
            }
                break;

            case 'filename': {
                $sql_field = 'cf.fullpath';
            }
                break;

            case 'labels': {
                $sql_field = "(SELECT $this->TextConcat AS labels FROM (SELECT label.text, coverage.fileid, coverage.buildid FROM label, label2coveragefile, coverage WHERE label2coveragefile.labelid=label.id AND label2coveragefile.buildid=coverage.buildid AND label2coveragefile.coveragefileid=coverage.fileid) AS filelabels WHERE fileid=c.fileid AND buildid=c.buildid)";
            }
                break;

            //case 'percentage':
            //{
            //  $sql_field = "TODO.percentage";
            //}
            //break;

            case 'priority': {
                $sql_field = 'cfp.priority';
            }
                break;

            //case 'status':
            //{
            //  $sql_field = "TODO.status";
            //}
            //break;

            case 'totallines': {
                $sql_field = '(c.loctested + c.locuntested)';
            }
                break;

            case 'uncoveredlines': {
                $sql_field = 'c.locuntested';
            }
                break;

            default:
                trigger_error('unknown $field value: ' . $field, E_USER_WARNING);
                break;
        }
        return $sql_field;
    }
}

class ViewTestPhpFilters extends DefaultFilters
{
    public function getDefaultFilter()
    {
        return array(
            'field' => 'testname',
            'fieldtype' => 'string',
            'compare' => 63,
            'value' => '',
        );
    }

    public function getFilterDefinitionsXML()
    {
        $xml = '';

        $xml .= getFilterDefinitionXML('details', 'Details', 'string', '', '');
        $xml .= getFilterDefinitionXML('label', 'Label', 'string', '', '');
        $xml .= getFilterDefinitionXML('status', 'Status', 'string', '', '');
        $xml .= getFilterDefinitionXML('testname', 'Test Name', 'string', '', '');
        $xml .= getFilterDefinitionXML('timestatus', 'Time Status', 'string', '', '');
        $xml .= getFilterDefinitionXML('time', 'Time', 'number', '', '');
        return $xml;
    }

    public function getSqlField($field)
    {
        $sql_field = '';
        switch (strtolower($field)) {
            case 'details': {
                $sql_field = 't.details';
            }
                break;

            case 'label': {
                $sql_field = "(SELECT $this->TextConcat FROM label, label2test WHERE label.id=label2test.labelid AND label2test.testid=t.id)";
            }
                break;

            case 'status': {
                $sql_field = 'bt.status';
            }
                break;

            case 'testname': {
                $sql_field = 't.name';
            }
                break;

            case 'timestatus': {
                $sql_field = 'bt.timestatus';
            }
                break;

            case 'time': {
                $sql_field = 'bt.time';
            }
                break;

            default:
                trigger_error('unknown $field value: ' . $field, E_USER_WARNING);
                break;
        }
        return $sql_field;
    }
}

class CompareCoveragePhpFilters extends DefaultFilters
{
    public function getDefaultFilter()
    {
        return array(
            'field' => 'subproject',
            'fieldtype' => 'string',
            'compare' => 61,
            'value' => ''
        );
    }

    public function getFilterDefinitionsXML()
    {
        $xml = '';
        $xml .= getFilterDefinitionXML('subproject', 'SubProject', 'string', '', '');
        return $xml;
    }

    public function getSqlField($field)
    {
        $sql_field = '';
        switch (strtolower($field)) {
            case 'subproject':
                $sql_field = 'sp.name';
                break;

            default:
                trigger_error("unknown field: $field", E_USER_WARNING);
                break;
        }
        return $sql_field;
    }
}

// Factory method to create page specific filters:
//
function createPageSpecificFilters($page_id)
{
    switch ($page_id) {
        case 'index.php':
        case 'project.php':
        case 'indexchildren.php':
            return new IndexPhpFilters();
            break;

        case 'queryTests.php':
            return new QueryTestsPhpFilters();
            break;

        case 'viewCoverage.php':
        case 'getviewcoverage.php':
            return new ViewCoveragePhpFilters();
            break;

        case 'viewTest.php':
            return new ViewTestPhpFilters();
            break;

        case 'compareCoverage.php':
            return new CompareCoveragePhpFilters();
            break;

        default:
            trigger_error('unknown $page_id value: ' . $page_id .
                ' Add a new subclass of DefaultFilters for ' . $page_id,
                E_USER_WARNING);
            return new DefaultFilters();
            break;
    }
}

// Take a php $filterdata structure and return it as an XML string representation
//
function filterdata_XML($filterdata)
{
    $debug = $filterdata['debug']; // '0' or '1' -- shows debug info in HTML output
    $filtercombine = $filterdata['filtercombine']; // 'OR' or 'AND'
    $filters = $filterdata['filters']; // an array
    $limit = $filterdata['limit']; // an integer
    $pageId = $filterdata['pageId']; // id of the "calling page"...
    $pageSpecificFilters = $filterdata['pageSpecificFilters']; // an instance of PageSpecificFilters
    $showfilters = $filterdata['showfilters']; // 0 or 1
    $showlimit = $filterdata['showlimit']; // 0 or 1
    $colorblind = $filterdata['colorblind']; // 0 or 1

    $xml = '<filterdata>';
    $xml .= add_XML_value('debug', $debug);
    $xml .= add_XML_value('filtercombine', $filtercombine);
    $xml .= add_XML_value('limit', $limit);
    $xml .= add_XML_value('pageId', $pageId);
    $xml .= add_XML_value('script', $_SERVER['SCRIPT_NAME']);
    $xml .= add_XML_value('showfilters', $showfilters);
    $xml .= add_XML_value('showlimit', $showlimit);
    $xml .= add_XML_value('colorblind', $colorblind);

    $xml .= '<filterdefinitions>';
    $xml .= $pageSpecificFilters->getFilterDefinitionsXML();
    $xml .= '</filterdefinitions>';

    $xml .= '<filters>';

    foreach ($filters as $filter) {
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

function get_sql_date_value($value)
{
    // transform from sql_value (assumed UTC)
    // to value (assumed server local timezone):
    //$ts = strtotime($sql_value." UTC");
    //$value = date(FMT_DATETIMETZ, $ts);

    // transform from value (could be anything, may be specified in the string)
    // to sql_value (UTC):
    $ts = strtotime($value);
    $sql_value = "'" . gmdate(FMT_DATETIME, $ts) . "'";
    return $sql_value;
}

// Translate "comparison operation" and "compare-to value" to SQL equivalents:
//
function get_sql_compare_and_value($compare, $value)
{
    $sql_compare = '';
    $sql_value = '';

    switch ($compare) {
        case 0:
            // bool do not compare
            // explicitly skip adding a clause when $compare == 0 ( "--" in the GUI )
            break;

        case 1: {
            // bool is true
            $sql_compare = '!=';
            $sql_value = '0';
        }
            break;

        case 2: {
            // bool is false
            $sql_compare = '=';
            $sql_value = '0';
        }
            break;

        case 40:
            // number do not compare
            // explicitly skip adding a clause when $compare == 40 ( "--" in the GUI )
            break;

        case 41: {
            // number is equal
            $sql_compare = '=';
            $sql_value = "'$value'";
        }
            break;

        case 42: {
            // number is not equal
            $sql_compare = '!=';
            $sql_value = "'$value'";
        }
            break;

        case 43: {
            // number is greater than
            $sql_compare = '>';
            $sql_value = "'$value'";
        }
            break;

        case 44: {
            // number is less than
            $sql_compare = '<';
            $sql_value = "'$value'";
        }
            break;

        case 60:
            // string do not compare
            // explicitly skip adding a clause when $compare == 60 ( "--" in the GUI )
            break;

        case 61: {
            // string is equal
            $sql_compare = '=';
            $sql_value = "'$value'";
        }
            break;

        case 62: {
            // string is not equal
            $sql_compare = '!=';
            $sql_value = "'$value'";
        }
            break;

        case 63: {
            // string contains
            $sql_compare = 'LIKE';
            $sql_value = "'%$value%'";
        }
            break;

        case 64: {
            // string does not contain
            $sql_compare = 'NOT LIKE';
            $sql_value = "'%$value%'";
        }
            break;

        case 65: {
            // string starts with
            $sql_compare = 'LIKE';
            $sql_value = "'$value%'";
        }
            break;

        case 66: {
            // string ends with
            $sql_compare = 'LIKE';
            $sql_value = "'%$value'";
        }
            break;

        case 80:
            // date do not compare
            // explicitly skip adding a clause when $compare == 80 ( "--" in the GUI )
            break;

        case 81: {
            // date is equal
            $sql_compare = '=';
            $sql_value = get_sql_date_value($value);
        }
            break;

        case 82: {
            // date is not equal
            $sql_compare = '!=';
            $sql_value = get_sql_date_value($value);
        }
            break;

        case 83: {
            // date is after
            $sql_compare = '>';
            $sql_value = get_sql_date_value($value);
        }
            break;

        case 84: {
            // date is before
            $sql_compare = '<';
            $sql_value = get_sql_date_value($value);
        }
            break;

        case 92:
        case 93: {
            // Include or exclude a SubProject from the parent summary.
            // This has to be handled via custom logic,
            // not just by tweaking the SQL query.
            $sql_compare = '';
            $sql_value = '';
        }
            break;

        default:
            trigger_error('unknown $compare value: ' . $compare, E_USER_WARNING);
            break;
    }
    return array($sql_compare, $sql_value);
}

// Analyze parameter values given in the URL _REQUEST and fill up a php
// $filterdata structure with them.
//
// For example, an input URL may look like this:
// http://www.cdash.org/CDash/index.php?project=CMake&filtercount=1
//   &showfilters=1&field1=buildname/string&compare1=63&value1=linux
//
// The $page_id parameter is used to decide what set of filter
// definitions to put in the returned $filterdata array. The default behavior
// is to use the calling php script name as the id. Known $page_id
// values include:
//   index.php
//   viewTest.php
//
function get_filterdata_from_request($page_id = '')
{
    global $CDASH_CSS_FILE;
    $sql = '';
    $xml = '';
    $clauses = 0;
    $filterdata = array();
    $filters = array();
    $add_filter = 0;
    $remove_filter = 0;
    $filterdata['hasdateclause'] = 0;

    if (empty($page_id)) {
        $pos = strrpos($_SERVER['SCRIPT_NAME'], '/');
        $page_id = substr($_SERVER['SCRIPT_NAME'], $pos + 1);
    }
    $filterdata['pageId'] = $page_id;

    $pageSpecificFilters = createPageSpecificFilters($page_id);
    $filterdata['pageSpecificFilters'] = $pageSpecificFilters;

    $filtercount = pdo_real_escape_numeric(@$_REQUEST['filtercount']);
    $showfilters = pdo_real_escape_numeric(@$_REQUEST['showfilters']);
    $showlimit = pdo_real_escape_numeric(@$_REQUEST['showlimit']);
    $limit = intval(pdo_real_escape_numeric(@$_REQUEST['limit']));
    if (!is_int($limit)) {
        $limit = 0;
    }

    @$clear = $_REQUEST['clear'];
    if ($clear == 'Clear') {
        $filtercount = 0;
    }

    @$filtercombine = htmlspecialchars(pdo_real_escape_string($_REQUEST['filtercombine']));
    if (strtolower($filtercombine) == 'or') {
        $sql_combine = 'OR';
    } else {
        $sql_combine = 'AND';
    }

    $sql = 'AND (';

    for ($i = 1; $i <= $filtercount; ++$i) {
        if (empty($_REQUEST['field' . $i])) {
            continue;
        }
        $fieldinfo = htmlspecialchars(pdo_real_escape_string($_REQUEST['field' . $i]));
        $compare = htmlspecialchars(pdo_real_escape_string($_REQUEST['compare' . $i]));
        $value = htmlspecialchars(pdo_real_escape_string($_REQUEST['value' . $i]));
        @$add = $_REQUEST['add' . $i];
        @$remove = $_REQUEST['remove' . $i];

        $fieldinfo = explode('/', $fieldinfo, 2);
        $field = $fieldinfo[0];

        if ($add == '+') {
            $add_filter = $i;
        } elseif ($remove == '-') {
            $remove_filter = $i;
        }

        $cv = get_sql_compare_and_value($compare, $value);
        $sql_compare = $cv[0];
        $sql_value = $cv[1];

        $sql_field = $pageSpecificFilters->getSqlField($field);

        if ($field == 'buildstarttime') {
            $filterdata['hasdateclause'] = 1;
        }

        // Treat the buildstamp field as if it were a date clause so that the
        // default date clause of "builds from today only" is not used...
        //
        if ($field == 'buildstamp') {
            $filterdata['hasdateclause'] = 1;
        }

        // Time durations can either be specified as a number of seconds,
        // or as a string representing a time interval.
        if (strpos($field, 'duration') !== false) {
            $input_value = trim($sql_value, "'");
            $sql_value = get_seconds_from_interval($input_value);
            if ($input_value !== $sql_value && $field === 'updateduration') {
                // Update duration is stored as number of minutes (not seconds)
                // so if we just converted this value from string to seconds
                // we should also convert it from seconds to minutes here as well.
                $sql_value /= 60.0;
            }
        }

        if ($sql_field != '' && $sql_compare != '') {
            if ($clauses > 0) {
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

    if ($clauses == 0) {
        $sql = '';
    } else {
        $sql .= ')';
    }

    // If no filters were passed in as parameters,
    // then add one default filter so that the user sees
    // somewhere to enter filter queries in the GUI:
    //
    if (0 == count($filters)) {
        $filters[] = $pageSpecificFilters->getDefaultFilter();
    }

    // If adding or removing a filter, do it before saving the
    // $filters array in the $filterdata...
    //
    if ($add_filter != 0) {
        $idx = $add_filter - 1;

        // Add a copy of the existing filter we are adding after:
        //
        $filter = $filters[$idx];

        array_splice($filters, $idx, 0, array($filter));
        //
        // with $length=0, array_splice is an "insert array element" call...
    }

    if ($remove_filter != 0) {
        $idx = $remove_filter - 1;

        array_splice($filters, $idx, 1);
        //
        // with $length=1, and no $replacement, array_splice is a "delete array
        // element" call...
    }

    // Fill up filterdata and return it:
    //
    @$debug = $_REQUEST['debug'];
    if ($debug) {
        $filterdata['debug'] = 1; // '0' or '1' -- shows debug info in HTML output
    } else {
        $filterdata['debug'] = 0;
    }

    $filterdata['filtercombine'] = $filtercombine;
    $filterdata['filters'] = $filters;
    $filterdata['limit'] = $limit;

    if ($showfilters) {
        $filterdata['showfilters'] = 1;
    } else {
        $filterdata['showfilters'] = 0;
    }

    if (array_key_exists('colorblind', $_COOKIE)) {
        $filterdata['colorblind'] = intval($_COOKIE['colorblind']);
    } else {
        if ($CDASH_CSS_FILE === 'css/colorblind.css') {
            $filterdata['colorblind'] = 1;
        } else {
            $filterdata['colorblind'] = 0;
        }
    }

    if (!array_key_exists('showlimit', $_REQUEST)) {
        $showlimit = $pageSpecificFilters->getDefaultShowLimit();
    }

    if ($showlimit) {
        $filterdata['showlimit'] = 1;
    } else {
        $filterdata['showlimit'] = 0;
    }

    $filterdata['sql'] = $sql;

    $xml = filterdata_XML($filterdata);

    $filterdata['xml'] = $xml;
    return $filterdata;
}

// Return a list of label IDs that match the specified filterdata.
function get_label_ids_from_filterdata($filterdata)
{
    $label_ids = array();
    $clauses = 0;
    $label_sql = '';
    $sql_combine = $filterdata['filtercombine'];

    foreach ($filterdata['filters'] as $filter) {
        if ($filter['field'] == 'label') {
            $cv = get_sql_compare_and_value($filter['compare'],
                    $filter['value']);
            $sql_compare = $cv[0];
            $sql_value = $cv[1];
            if ($clauses > 0) {
                $label_sql .= " $sql_combine ";
            }
            $label_sql .= "text $sql_compare $sql_value";
            ++$clauses;
        }
    }

    if ($clauses > 0) {
        $query_sql = "SELECT id FROM label WHERE $label_sql";
        $result = pdo_query($query_sql);
        while ($row = pdo_fetch_array($result)) {
            $label_ids[] = $row['id'];
        }
    }

    return $label_ids;
}

// Return a sanitized string of filter parameters to be used in a URL.
function get_filterurl()
{
    if (!array_key_exists('filterstring', $_GET)) {
        return '';
    }

    // htmlentities is used here to prevent XSS injection from filterstring content.
    $filterurl = htmlentities($_GET['filterstring'], ENT_QUOTES);
    // ...but we need ampersands to pass through unescaped, so convert them back.
    $filterurl = str_replace('&amp;', '&', $filterurl);
    return $filterurl;
}

// Returns true if the build should be included based on the specified filters,
// false otherwise.
function build_survives_filter($build_response, $filterdata)
{
    $filters = $filterdata['filters'];
    foreach ($filters as $filter) {
        // Get the filter's value for comparison.
        $filter_value = $filter['value'];

        // Get the build value that's relevant to this filter.
        // (number of configure warnings, number of test failures, etc.)
        $build_value = false;
        switch ($filter['field']) {
            case 'buildduration':
                $filter_value = get_seconds_from_interval($filter_value);
                if ($build_response['hascompilation']) {
                    $build_value = $build_response['compilation']['timefull'];
                }
                break;

            case 'builderrors':
                if ($build_response['hascompilation']) {
                    $build_value = $build_response['compilation']['error'];
                }
                break;

            case 'buildwarnings':
                if ($build_response['hascompilation']) {
                    $build_value = $build_response['compilation']['warning'];
                }
                break;

            case 'configureduration':
                $filter_value = get_seconds_from_interval($filter_value);
                if ($build_response['hasconfigure']) {
                    $build_value = $build_response['configure']['timefull'];
                }
                break;

            case 'configureerrors':
                if ($build_response['hasconfigure']) {
                    $build_value = $build_response['configure']['error'];
                }
                break;

            case 'configurewarnings':
                if ($build_response['hasconfigure']) {
                    $build_value = $build_response['configure']['warning'];
                }
                break;

            case 'testsduration':
                $filter_value = get_seconds_from_interval($filter_value);
                if ($build_response['hastest']) {
                    $build_value = $build_response['test']['timefull'];
                }
                break;

            case 'testsfailed':
                if ($build_response['hastest']) {
                    $build_value = $build_response['test']['fail'];
                }
                break;

            case 'testsnotrun':
                if ($build_response['hastest']) {
                    $build_value = $build_response['test']['notrun'];
                }
                break;

            case 'testspassed':
                if ($build_response['hastest']) {
                    $build_value = $build_response['test']['pass'];
                }
                break;

            case 'testtimestatus':
                if ($build_response['hastest']) {
                    $build_value = $build_response['test']['timestatus'];
                }
                break;

            default:
                continue;
                break;
        }

        // Compare the build & filter's values, returning false if
        // they don't match the filter's expectation.
        switch ($filter['compare']) {
            case 41:
                // The filter expects the numbers to be equal.
                if ($build_value != $filter_value) {
                    return false;
                }
                break;

            case 42:
                // The filter expects the numbers to not be equal.
                if ($build_value == $filter_value) {
                    return false;
                }
                break;

            case 43:
                // The filter expects the build value to be greater.
                if ($build_value <= $filter_value) {
                    return false;
                }
                break;

            case 44:
                // The filter expects the build value to be lesser.
                if ($build_value >= $filter_value) {
                    return false;
                }
                break;

            default:
                break;
        }
    }
    return true;
}
