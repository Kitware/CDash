<?php
/**
 *  base include file for SimpleTest
 *  @package    SimpleTest
 *  @subpackage UnitTester
 *  @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once(dirname(__FILE__) . '/scorer.php');
/**#@-*/

/**
 *    Creates the XML needed for remote communication
 *    by SimpleTest.
 *    @package SimpleTest
 *    @subpackage UnitTester
 */
class XmlReporter extends SimpleReporter {
    var $_indent;
    var $_namespace;

    /**
     *    Sets up indentation and namespace.
     *    @param string $namespace        Namespace to add to each tag.
     *    @param string $indent           Indenting to add on each nesting.
     *    @access public
     */
    function XmlReporter($namespace = false, $indent = '  ') {
        $this->SimpleReporter();
        $this->_namespace = ($namespace ? $namespace . ':' : '');
        $this->_indent = $indent;
    }

    /**
     *    Calculates the pretty printing indent level
     *    from the current level of nesting.
     *    @param integer $offset  Extra indenting level.
     *    @return string          Leading space.
     *    @access protected
     */
    function _getIndent($offset = 0) {
        return str_repeat(
                $this->_indent,
                count($this->getTestList()) + $offset);
    }

    /**
     *    Converts character string to parsed XML
     *    entities string.
     *    @param string text        Unparsed character data.
     *    @return string            Parsed character data.
     *    @access public
     */
    function toParsedXml($text) {
        return str_replace(
                array('&', '<', '>', '"', '\''),
                array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;'),
                $text);
    }

    /**
     *    Paints the start of a group test.
     *    @param string $test_name   Name of test that is starting.
     *    @param integer $size       Number of test cases starting.
     *    @access public
     */
    function paintGroupStart($test_name, $size) {
        parent::paintGroupStart($test_name, $size);
        print $this->_getIndent();
        print "<" . $this->_namespace . "group size=\"$size\">\n";
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "name>" .
                $this->toParsedXml($test_name) .
                "</" . $this->_namespace . "name>\n";
    }

    /**
     *    Paints the end of a group test.
     *    @param string $test_name   Name of test that is ending.
     *    @access public
     */
    function paintGroupEnd($test_name) {
        print $this->_getIndent();
        print "</" . $this->_namespace . "group>\n";
        parent::paintGroupEnd($test_name);
    }

    /**
     *    Paints the start of a test case.
     *    @param string $test_name   Name of test that is starting.
     *    @access public
     */
    function paintCaseStart($test_name) {
        parent::paintCaseStart($test_name);
        print $this->_getIndent();
        print "<" . $this->_namespace . "case>\n";
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "name>" .
                $this->toParsedXml($test_name) .
                "</" . $this->_namespace . "name>\n";
    }

    /**
     *    Paints the end of a test case.
     *    @param string $test_name   Name of test that is ending.
     *    @access public
     */
    function paintCaseEnd($test_name) {
        print $this->_getIndent();
        print "</" . $this->_namespace . "case>\n";
        parent::paintCaseEnd($test_name);
    }

    /**
     *    Paints the start of a test method.
     *    @param string $test_name   Name of test that is starting.
     *    @access public
     */
    function paintMethodStart($test_name) {
        parent::paintMethodStart($test_name);
        print $this->_getIndent();
        print "<" . $this->_namespace . "test>\n";
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "name>" .
                $this->toParsedXml($test_name) .
                "</" . $this->_namespace . "name>\n";
    }

    /**
     *    Paints the end of a test method.
     *    @param string $test_name   Name of test that is ending.
     *    @param integer $progress   Number of test cases ending.
     *    @access public
     */
    function paintMethodEnd($test_name) {
        print $this->_getIndent();
        print "</" . $this->_namespace . "test>\n";
        parent::paintMethodEnd($test_name);
    }

    /**
     *    Paints pass as XML.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintPass($message) {
        parent::paintPass($message);
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "pass>";
        print $this->toParsedXml($message);
        print "</" . $this->_namespace . "pass>\n";
    }

    /**
     *    Paints failure as XML.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintFail($message) {
        parent::paintFail($message);
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "fail>";
        print $this->toParsedXml($message);
        print "</" . $this->_namespace . "fail>\n";
    }

    /**
     *    Paints error as XML.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintError($message) {
        parent::paintError($message);
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "exception>";
        print $this->toParsedXml($message);
        print "</" . $this->_namespace . "exception>\n";
    }

    /**
     *    Paints exception as XML.
     *    @param Exception $exception    Exception to encode.
     *    @access public
     */
    function paintException($exception) {
        parent::paintException($exception);
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "exception>";
        $message = 'Unexpected exception of type [' . get_class($exception) .
                '] with message ['. $exception->getMessage() .
                '] in ['. $exception->getFile() .
                ' line ' . $exception->getLine() . ']';
        print $this->toParsedXml($message);
        print "</" . $this->_namespace . "exception>\n";
    }

    /**
     *    Paints the skipping message and tag.
     *    @param string $message        Text to display in skip tag.
     *    @access public
     */
    function paintSkip($message) {
        parent::paintSkip($message);
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "skip>";
        print $this->toParsedXml($message);
        print "</" . $this->_namespace . "skip>\n";
    }

    /**
     *    Paints a simple supplementary message.
     *    @param string $message        Text to display.
     *    @access public
     */
    function paintMessage($message) {
        parent::paintMessage($message);
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "message>";
        print $this->toParsedXml($message);
        print "</" . $this->_namespace . "message>\n";
    }

    /**
     *    Paints a formatted ASCII message such as a
     *    variable dump.
     *    @param string $message        Text to display.
     *    @access public
     */
    function paintFormattedMessage($message) {
        parent::paintFormattedMessage($message);
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "formatted>";
        print "<![CDATA[$message]]>";
        print "</" . $this->_namespace . "formatted>\n";
    }

    /**
     *    Serialises the event object.
     *    @param string $type        Event type as text.
     *    @param mixed $payload      Message or object.
     *    @access public
     */
    function paintSignal($type, $payload) {
        parent::paintSignal($type, $payload);
        print $this->_getIndent(1);
        print "<" . $this->_namespace . "signal type=\"$type\">";
        print "<![CDATA[" . serialize($payload) . "]]>";
        print "</" . $this->_namespace . "signal>\n";
    }

    /**
     *    Paints the test document header.
     *    @param string $test_name     First test top level
     *                                 to start.
     *    @access public
     *    @abstract
     */
    function paintHeader($test_name) {
        if (! SimpleReporter::inCli()) {
            header('Content-type: text/xml');
        }
        print "<?xml version=\"1.0\"";
        if ($this->_namespace) {
            print " xmlns:" . $this->_namespace .
                    "=\"www.lastcraft.com/SimpleTest/Beta3/Report\"";
        }
        print "?>\n";
        print "<" . $this->_namespace . "run>\n";
    }

    /**
     *    Paints the test document footer.
     *    @param string $test_name        The top level test.
     *    @access public
     *    @abstract
     */
    function paintFooter($test_name) {
        print "</" . $this->_namespace . "run>\n";
    }
}

/**
 *    Accumulator for incoming tag. Holds the
 *    incoming test structure information for
 *    later dispatch to the reporter.
 *    @package SimpleTest
 *    @subpackage UnitTester
 */
class NestingXmlTag {
    var $_name;
    var $_attributes;

    /**
     *    Sets the basic test information except
     *    the name.
     *    @param hash $attributes   Name value pairs.
     *    @access public
     */
    function NestingXmlTag($attributes) {
        $this->_name = false;
        $this->_attributes = $attributes;
    }

    /**
     *    Sets the test case/method name.
     *    @param string $name        Name of test.
     *    @access public
     */
    function setName($name) {
        $this->_name = $name;
    }

    /**
     *    Accessor for name.
     *    @return string        Name of test.
     *    @access public
     */
    function getName() {
        return $this->_name;
    }

    /**
     *    Accessor for attributes.
     *    @return hash        All attributes.
     *    @access protected
     */
    function _getAttributes() {
        return $this->_attributes;
    }
}

/**
 *    Accumulator for incoming method tag. Holds the
 *    incoming test structure information for
 *    later dispatch to the reporter.
 *    @package SimpleTest
 *    @subpackage UnitTester
 */
class NestingMethodTag extends NestingXmlTag {

    /**
     *    Sets the basic test information except
     *    the name.
     *    @param hash $attributes   Name value pairs.
     *    @access public
     */
    function NestingMethodTag($attributes) {
        $this->NestingXmlTag($attributes);
    }

    /**
     *    Signals the appropriate start event on the
     *    listener.
     *    @param SimpleReporter $listener    Target for events.
     *    @access public
     */
    function paintStart(&$listener) {
        $listener->paintMethodStart($this->getName());
    }

    /**
     *    Signals the appropriate end event on the
     *    listener.
     *    @param SimpleReporter $listener    Target for events.
     *    @access public
     */
    function paintEnd(&$listener) {
        $listener->paintMethodEnd($this->getName());
    }
}

/**
 *    Accumulator for incoming case tag. Holds the
 *    incoming test structure information for
 *    later dispatch to the reporter.
 *    @package SimpleTest
 *    @subpackage UnitTester
 */
class NestingCaseTag extends NestingXmlTag {

    /**
     *    Sets the basic test information except
     *    the name.
     *    @param hash $attributes   Name value pairs.
     *    @access public
     */
    function NestingCaseTag($attributes) {
        $this->NestingXmlTag($attributes);
    }

    /**
     *    Signals the appropriate start event on the
     *    listener.
     *    @param SimpleReporter $listener    Target for events.
     *    @access public
     */
    function paintStart(&$listener) {
        $listener->paintCaseStart($this->getName());
    }

    /**
     *    Signals the appropriate end event on the
     *    listener.
     *    @param SimpleReporter $listener    Target for events.
     *    @access public
     */
    function paintEnd(&$listener) {
        $listener->paintCaseEnd($this->getName());
    }
}

/**
 *    Accumulator for incoming group tag. Holds the
 *    incoming test structure information for
 *    later dispatch to the reporter.
 *    @package SimpleTest
 *    @subpackage UnitTester
 */
class NestingGroupTag extends NestingXmlTag {

    /**
     *    Sets the basic test information except
     *    the name.
     *    @param hash $attributes   Name value pairs.
     *    @access public
     */
    function NestingGroupTag($attributes) {
        $this->NestingXmlTag($attributes);
    }

    /**
     *    Signals the appropriate start event on the
     *    listener.
     *    @param SimpleReporter $listener    Target for events.
     *    @access public
     */
    function paintStart(&$listener) {
        $listener->paintGroupStart($this->getName(), $this->getSize());
    }

    /**
     *    Signals the appropriate end event on the
     *    listener.
     *    @param SimpleReporter $listener    Target for events.
     *    @access public
     */
    function paintEnd(&$listener) {
        $listener->paintGroupEnd($this->getName());
    }

    /**
     *    The size in the attributes.
     *    @return integer     Value of size attribute or zero.
     *    @access public
     */
    function getSize() {
        $attributes = $this->_getAttributes();
        if (isset($attributes['SIZE'])) {
            return (integer)$attributes['SIZE'];
        }
        return 0;
    }
}

/**
 *    Parser for importing the output of the XmlReporter.
 *    Dispatches that output to another reporter.
 *    @package SimpleTest
 *    @subpackage UnitTester
 */
class SimpleTestXmlParser {
    var $_listener;
    var $_expat;
    var $_tag_stack;
    var $_in_content_tag;
    var $_content;
    var $_attributes;

    /**
     *    Loads a listener with the SimpleReporter
     *    interface.
     *    @param SimpleReporter $listener   Listener of tag events.
     *    @access public
     */
    function SimpleTestXmlParser(&$listener) {
        $this->_listener = &$listener;
        $this->_expat = &$this->_createParser();
        $this->_tag_stack = array();
        $this->_in_content_tag = false;
        $this->_content = '';
        $this->_attributes = array();
    }

    /**
     *    Parses a block of XML sending the results to
     *    the listener.
     *    @param string $chunk        Block of text to read.
     *    @return boolean             True if valid XML.
     *    @access public
     */
    function parse($chunk) {
        if (! xml_parse($this->_expat, $chunk)) {
            trigger_error('XML parse error with ' .
                    xml_error_string(xml_get_error_code($this->_expat)));
            return false;
        }
        return true;
    }

    /**
     *    Sets up expat as the XML parser.
     *    @return resource        Expat handle.
     *    @access protected
     */
    function &_createParser() {
        $expat = xml_parser_create();
        xml_set_object($expat, $this);
        xml_set_element_handler($expat, '_startElement', '_endElement');
        xml_set_character_data_handler($expat, '_addContent');
        xml_set_default_handler($expat, '_default');
        return $expat;
    }

    /**
     *    Opens a new test nesting level.
     *    @return NestedXmlTag     The group, case or method tag
     *                             to start.
     *    @access private
     */
    function _pushNestingTag($nested) {
        array_unshift($this->_tag_stack, $nested);
    }

    /**
     *    Accessor for current test structure tag.
     *    @return NestedXmlTag     The group, case or method tag
     *                             being parsed.
     *    @access private
     */
    function &_getCurrentNestingTag() {
        return $this->_tag_stack[0];
    }

    /**
     *    Ends a nesting tag.
     *    @return NestedXmlTag     The group, case or method tag
     *                             just finished.
     *    @access private
     */
    function _popNestingTag() {
        return array_shift($this->_tag_stack);
    }

    /**
     *    Test if tag is a leaf node with only text content.
     *    @param string $tag        XML tag name.
     *    @return @boolean          True if leaf, false if nesting.
     *    @private
     */
    function _isLeaf($tag) {
        return in_array($tag, array(
                'NAME', 'PASS', 'FAIL', 'EXCEPTION', 'SKIP', 'MESSAGE', 'FORMATTED', 'SIGNAL'));
    }

    /**
     *    Handler for start of event element.
     *    @param resource $expat     Parser handle.
     *    @param string $tag         Element name.
     *    @param hash $attributes    Name value pairs.
     *                               Attributes without content
     *                               are marked as true.
     *    @access protected
     */
    function _startElement($expat, $tag, $attributes) {
        $this->_attributes = $attributes;
        if ($tag == 'GROUP') {
            $this->_pushNestingTag(new NestingGroupTag($attributes));
        } elseif ($tag == 'CASE') {
            $this->_pushNestingTag(new NestingCaseTag($attributes));
        } elseif ($tag == 'TEST') {
            $this->_pushNestingTag(new NestingMethodTag($attributes));
        } elseif ($this->_isLeaf($tag)) {
            $this->_in_content_tag = true;
            $this->_content = '';
        }
    }

    /**
     *    End of element event.
     *    @param resource $expat     Parser handle.
     *    @param string $tag         Element name.
     *    @access protected
     */
    function _endElement($expat, $tag) {
        $this->_in_content_tag = false;
        if (in_array($tag, array('GROUP', 'CASE', 'TEST'))) {
            $nesting_tag = $this->_popNestingTag();
            $nesting_tag->paintEnd($this->_listener);
        } elseif ($tag == 'NAME') {
            $nesting_tag = &$this->_getCurrentNestingTag();
            $nesting_tag->setName($this->_content);
            $nesting_tag->paintStart($this->_listener);
        } elseif ($tag == 'PASS') {
            $this->_listener->paintPass($this->_content);
        } elseif ($tag == 'FAIL') {
            $this->_listener->paintFail($this->_content);
        } elseif ($tag == 'EXCEPTION') {
            $this->_listener->paintError($this->_content);
        } elseif ($tag == 'SKIP') {
            $this->_listener->paintSkip($this->_content);
        } elseif ($tag == 'SIGNAL') {
            $this->_listener->paintSignal(
                    $this->_attributes['TYPE'],
                    unserialize($this->_content));
        } elseif ($tag == 'MESSAGE') {
            $this->_listener->paintMessage($this->_content);
        } elseif ($tag == 'FORMATTED') {
            $this->_listener->paintFormattedMessage($this->_content);
        }
    }

    /**
     *    Content between start and end elements.
     *    @param resource $expat     Parser handle.
     *    @param string $text        Usually output messages.
     *    @access protected
     */
    function _addContent($expat, $text) {
        if ($this->_in_content_tag) {
            $this->_content .= $text;
        }
        return true;
    }

    /**
     *    XML and Doctype handler. Discards all such content.
     *    @param resource $expat     Parser handle.
     *    @param string $default     Text of default content.
     *    @access protected
     */
    function _default($expat, $default) {
    }
}

    /**
     *   XML reporter specific to use CDash
     *   Allows :
     *            *   a user to test his php code and
     *            send to the dashboard.
     *            *   update his working repository before
     *            to send his test to cdash
     */
class CDashXmlReporter extends XmlReporter
{
  // Configuration parameters
  var $_configure         = null;
  // The file resource that is returned by the system
  // when we create/open/write into a file
  var $_testfile          = null;
  // The file resource that is returned by the system
  // when we create/open/write into a file
  var $_buildfile         = null;
  // The file resource that is returned by the system
  // when we create/open/write into a file
  var $_updatefile        = null; 
  // The test name (required for CDash)
  var $_testname          = null;
  // The path (to the directory uppon the test file)
  var $_testpath          = null;
  // The number of the testfile associated to the file
  // which iscurrently running
  var $_testN              = null;
  // The number of the method test
  var $_methodN           = null;
  // The time in sec at the begining of a test method
  // (required by CDash to compute the execution time)
  var $_start_method_time = null;
  // The execution time associate to a test method (required by CDash)
  var $_execution_time    = null;
  // The cumulative execution time in sec (required by CDash in minute)
  var $_elapsedminutes    = null;
  // The status of a test (passed or failed <=> Completed //
  //                       error or exception <=> Interrupted == Uncompleted)
  var $_teststatus        = null;
  // The path to cake library (required by CDash to print the command line)
  var $_corepath          = null;
  // The url to cdash server if we have to send the report to cdash
  var $_urlToCdash       = null;

  /**
     * the constructor : initialize the variables that needs to be initialized
     *                   create/open the xml test file
     */
  function __construct($configure)
    {
    parent::__construct();
    $this->_configure     = $configure;
    $filename             = $this->_configure['outputdirectory'].'/Test.xml';
    $this->_testfile      = fopen($filename,'w+');
    $filename             = $this->_configure['outputdirectory'].'/Build.xml';
    $this->_buildfile     = fopen($filename,'w+');
    $filename             = $this->_configure['outputdirectory'].'/Update.xml';
    $this->_updatefile    = fopen($filename,'w+');
    $this->_testN         = 0;
    $this->_methodN       = 0;
    $this->_elapsedminutes = 0; 
    $this->paintStartCDashTest();
    $this->paintGetDateTime();
    }

  /**
     * the destructor : close the xml file
     */
  function __destruct()
    {
    $this->paintEndCDashTest();
    fclose($this->_testfile);
    fclose($this->_buildfile);
    fclose($this->_updatefile);
    if(!empty($this->_urlToCdash))
      {
      $this->_sendToCdash();  
      }
    }

  /**
     * paintStartCDashTest: print into the file the common begining of
     *                      all xml cdash file
     */
  function paintStartCDashTest()
    {
    $this->paintHeader();
    $this->paintSiteTag();
    fwrite($this->_testfile, "<".$this->_namespace."Testing>\n");
    fwrite($this->_buildfile, "<".$this->_namespace."Build>\n");
    }

  function paintSiteTag()
    {
    $this->_paintSiteTag($this->_testfile);
    $this->_paintSiteTag($this->_buildfile);
    }

  function _paintSiteTag($resourcefile)
    {
    fwrite($resourcefile,"<".$this->_namespace.'Site BuildName="'.$this->_configure['buildname'].'" BuildStamp="'.date("Ymd").'-0100-'.$this->_configure['type'].'" Name="'.$this->_configure['site'].'" Generator="simpletest1.0.1">'."\n");
    }

  /**
     * paintHeader: print the xml header for xml cdash file
     */
  function paintHeader($test_name = NULL)
    {
    $this->_paintHeader($this->_testfile);
    $this->_paintHeader($this->_buildfile);
    $this->_paintHeader($this->_updatefile);
    }

  /**
     * _paintHeader: print the xml header
     */
  function _paintHeader($resourcefile)
    {
    fwrite($resourcefile,'<?xml version="1.0" encoding="UTF-8"?>'."\n");
    }

  /**
     * paintGetDateTime: print the time when the test is starting
     */
  function paintGetDateTime()
    {
    $this->_paintGetDateTime($this->_testfile);
    $this->_paintGetDateTime($this->_buildfile);
    $this->_paintBuildInformation($this->_buildfile);
    }

  /**
     * paintGetDateTime: print the time when the test is starting
     */
  function _paintGetDateTime($resourcefile)
    {
    fwrite($resourcefile, $this->_getIndent(1));
    fwrite($resourcefile, "<".$this->_namespace."StartDateTime>");
    fwrite($resourcefile,date("M d G:i T"));
    fwrite($resourcefile, "</StartDateTime>\n");
    }


  /**
     *    paint all the information related to the build condition
     *    @param string $test_name   Name of test that is starting.
     *    @access protected
     */
  function _paintBuildInformation($resourcefile)
    {
    fwrite($resourcefile, $this->_getIndent(1));
    fwrite($resourcefile,"<".$this->_namespace."StartBuildTime>".time()."</StartBuildTime>\n");
    fwrite($resourcefile, $this->_getIndent(1));
    fwrite($resourcefile,"<".$this->_namespace."BuildCommand>php5 ".realpath('./AllTest.php')."</BuildCommand>\n");
    }

  /**
     * paintTestCaseList: print the list of the test into the tag <TestList>
     */
   function paintTestCaseList($List)
     {
     fwrite($this->_testfile, $this->_getIndent(1));
     fwrite($this->_testfile, "<".$this->_namespace."TestList>\n");
     $this->_testpath = array();
     foreach($List as $key=>$test)
       {
       $this->paintTestFunction($key);
       $this->_testpath[] = $key;
       }
     fwrite($this->_testfile, $this->_getIndent(1));
     fwrite($this->_testfile, "</TestList>\n");
     }

  /**
     * paintTestCasefunction: print each name test into <Test> tag
     */
   function paintTestFunction($path_to_test)
     {
       $methods = $this->getTestFunction($path_to_test);
       foreach($methods as $method)
         {
        fwrite($this->_testfile, $this->_getIndent(2));
         fwrite($this->_testfile, "<".$this->_namespace."Test>$method</Test>\n");
         }
     }


  /**
     *    Paints nothing for the start of a group test. For CDash, we
     *    do not need this option
     *    @param string $test_name   Name of test that is starting.
     *    @param integer $size       Number of test cases starting.
     *    @access public
     */
  function paintGroupStart($test_name, $size)
    {
    }


  /**
     *    Paints nothing for the end of a group test. For CDash, we
     *    do not need this option
     *    @param string $test_name   Name of test that is ending.
     *    @access public
     */
    function paintGroupEnd($test_name)
      {
      }

  /**
     *    Paints nothing for the end of a test case. For CDash, we
     *    do not need this option
     *    @param string $test_name   Name of test that is starting.
     *    @access public
     */
    function paintCaseStart($test_name)
      {
      }

  /**
     *    Paints nothing for the end of a test case. For CDash, we
     *    do not need this option
     *    @param string $test_name   Name of test that is ending.
     *    @access public
     */
    function paintCaseEnd($test_name)
      {
      ++$this->_testN;
      }



  /**
     *    Paints the start of a test method.
     *    @param string $test_name   Name of test that is starting.
     *    @access public
     */
    function paintMethodStart($test_name) {
      echo $this->_methodN."/ Testing $test_name";
      $this->_testname       = $test_name;
      $this->_start_method_time = $start = (float) array_sum(explode(' ',microtime()));
    }

  /**
     *    Paints the end of a test method.
     *    @param string $test_name   Name of test that is ending.
     *    @param integer $progress   Number of test cases ending.
     *    @access public
     */
    function paintMethodEnd($test_name) {
        $this->_testname = '';
        $this->_methodN++; 
      }

  /**
     *    Set the url of the CDash server     
     *    @param string $url  url via we make the curl to send the report
     */
    
    function setCDashServer(){
      if(!empty($this->_configure['cdash']))
       {
       $this->_urlToCdash = $this->_configure['cdash'];
       }
    }
  
  
     function updateSVN(){
      if(!empty($this->_configure['cdash']))
       {
       $this->_paintSvnUpdateStart();
       $this->__performSvnUpdate();
       }
    }
  
    
  /**
     *     paint the start of the update.xml for CDash 
     */
    function _paintSvnUpdateStart(){
      fwrite($this->_updatefile,"<".$this->_namespace.'Update mode="Client" Generator="simpletest1.0.1"'.">\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."Site>".$this->_configure['site']."</Site>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."BuildName>".$this->_configure['buildname']."</BuildName>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."BuildStamp>".date("Ymd").'-0100-'.$this->_configure['type']."</BuildStamp>\n");
      $this->_paintGetDateTime($this->_updatefile);
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."StartTime>".time()."</StartTime>\n");
      // We get the command line
      $commandline = `which svn`;
      $commandline = explode("\n", $commandline);
      $commandline = '"'.$commandline[0].'"';
      $commandline .= ' info';
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."UpdateCommand>".$commandline."</UpdateCommand>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."UpdateType>SVN</UpdateType>\n");
    }
    
  /**
     *    perform an update of a revision in the svn 
     */
    function __performSvnUpdate(){
      $svnroot = $this->_configure['svnroot'];
      $this->_start_method_time = (float) array_sum(explode(' ',microtime()));
      $raw_output = $this->__performSvnCommand(`svn info $svnroot 2>&1 | grep Revision`);
      // We catch the current revision of the repository
      $currentRevision = str_replace('Revision: ','',$raw_output[0]);
      $raw_output = $this->__performSvnCommand(`svn update $svnroot 2>&1 | grep revision`);
      if(strpos($raw_output[0],'At revision') !== false)
       {
       $this->computeTimeExecution();
       $this->_paintSvnUpdateEnd();
       echo "Old revision of repository is: $currentRevision\nCurrent revision of repository is: $currentRevision\n";
       echo "Project is up to date\n";
       return;
       }
      $newRevision = str_replace('Updated to revision ','',$raw_output[0]);
      $newRevision = strtok($newRevision,'.');
      $raw_output = `svn log $svnroot -r $currentRevision:$newRevision -v --xml 2>&1`;
      $this->_paintSvnUpdateFile($raw_output);
      $this->computeTimeExecution();
      $this->_paintSvnUpdateEnd();
      echo "Your Repository has just been updating from revision $currentRevision to revision $newRevision\n";
      echo "\tRepository concerned: $svnroot\n\tUse SVN repository type\n";
      echo "Project is up to date\n";
    }


    function __performSvnCommand($commandline)
    {
      return explode("\n", $commandline);
    }

    function _paintSvnUpdateFile($xmlstr)
    {
      $xml = new SimpleXMLElement($xmlstr);
      $sum = array();
      foreach($xml->logentry as $entry)
        {
        foreach($entry->paths->path as $file)
          {
          $pathinfo = pathinfo($file);
          $pathdir  = str_replace('/trunk/','',$pathinfo['dirname']);
          $filename = $pathinfo['basename'];
          $date = strtok($entry->date,'.');
          $author = $entry->author;
          $revision = $entry['revision']; 
          $this->_paintSvnUpdateDirectory($pathdir,$filename,$date,$author,$revision);
          $sum[(string)$entry->author][] = $pathdir.'/'.$filename ;
          }
        }
      foreach($sum as $key => $author)
        {
        $this->_paintSvnUpdateAuthor($key,$author);
        }
    }
    
   function _paintSvnUpdateDirectory($path,$filename,$date,$author,$revision)
   {
       fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."Directory>\n");
      fwrite($this->_updatefile, $this->_getIndent(2));
      fwrite($this->_updatefile,"<".$this->_namespace."Name>$path</Name>\n");
      fwrite($this->_updatefile, $this->_getIndent(2));
      fwrite($this->_updatefile,"<".$this->_namespace."Updated>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace.'File Directory="'.$path.'">'.$filename."</File>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Directory>$path</Directory>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."FullName>$path/$filename</FullName>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."CheckinDate>$date</CheckinDate>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Author>$author</Author>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Email>Unknown</Email>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Log></Log>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Revision>$revision</Revision>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."PriorRevision>$revision</PriorRevision>\n");
      fwrite($this->_updatefile, $this->_getIndent(2));
      fwrite($this->_updatefile,"<".$this->_namespace."/Updated>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."/Directory>\n");
   }
   
   function _paintSvnUpdateAuthor($author,$files)
   {
       fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."Author>\n");
      fwrite($this->_updatefile, $this->_getIndent(2));
      fwrite($this->_updatefile,"<".$this->_namespace."Name>$author</Name>\n");
      foreach($files as $file)
        {
        fwrite($this->_updatefile, $this->_getIndent(2));
        $directory = substr($file,0,strrpos($file,'/'));
        $filename  = substr($file,strrpos($file,'/')+1);
        fwrite($this->_updatefile,"<".$this->_namespace.'File Directory="'.$directory.'">'.$filename."</File>\n");
        }
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."/Author>\n");
   }
    
 /**
    *     paint the end of the update.xml for CDash 
    */
    function _paintSvnUpdateEnd(){
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."EndDateTime>".date("M d G:i T")."</EndDateTime>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."EndTime>".time()."</EndTime>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      $elapsedMinutes = round($this->_elapsedminutes / 60 , 3);
      fwrite($this->_updatefile,"<".$this->_namespace."ElapsedMinutes>".$elapsedMinutes."</ElapsedMinutes>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."UpdateReturnStatus></UpdateReturnStatus>\n");
      fwrite($this->_updatefile,"<".$this->_namespace."/Update>\n");
      // We reinitialize for the testing
      $this->_elapsedminutes = 0;
    }
    
    
  /**
     *    Send via a curl to the CDash server the report test_cdash.xml and build_cdash.xml     
     *    @return true on success / false on failure
     */
    function _sendToCdash(){
      $msg = "Submit files (using http)\n\tUsing HTTP submit method\n\t";
      $msg .= "Drop site: ".$this->_configure['cdash']."?project=CDash\n";
      echo $msg;
      $filename = $this->_configure['outputdirectory'].'/Build.xml';
      $this->__uploadViaCurl($filename);
      echo "\tUploaded: ".$this->_configure['outputdirectory']."/Build.xml\n";
      $filename = $this->_configure['outputdirectory'].'/Test.xml';
      $this->__uploadViaCurl($filename);
      echo "\tUploaded: ".$this->_configure['outputdirectory']."/Test.xml\n";
      $filename = $this->_configure['outputdirectory'].'/Update.xml';
      $this->__uploadViaCurl($filename);
      echo "\tUploaded: ".$this->_configure['outputdirectory']."/Update.xml\n";
      echo "\tSubmission successful\n";
      return true;
    }
    
  /**
     *    Perform a curl to upload the filename to the CDash Server
     *    @param object $filename
   */
    function __uploadViaCurl($filename){
      $fp = fopen($filename, 'r');
      $ch = curl_init($this->_urlToCdash.'/submit.php?project=CDash');
      curl_setopt($ch, CURLOPT_TIMEOUT, 60);
      curl_setopt($ch, CURLOPT_UPLOAD, 1);
      curl_setopt($ch, CURLOPT_INFILE, $fp);
      curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
      curl_exec($ch);
      curl_close($ch);
      fclose($fp);
    }
    

  /**
     *    Paints pass as XML.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintPass($message) {
      $this->computeTimeExecution();
      $this->_teststatus = "Completed";
      if (!is_null($this->_testfile))
        {
        fwrite($this->_testfile, $this->_getIndent(1));
        fwrite($this->_testfile, "<" . $this->_namespace . "Test Status=".'"passed">'."\n");
        $this->_paintTestInfo($message);
        fwrite($this->_testfile, $this->_getIndent(1));
        fwrite($this->_testfile, "</" . $this->_namespace . "Test>\n");
        }
      echo "... Passed\n";
    }

  /**
     *    Paints failure as XML.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintFail($message) {
        $this->computeTimeExecution();
        $this->_teststatus = "Completed";
        if (!is_null($this->_testfile))
          {
          fwrite($this->_testfile, $this->_getIndent(1));
          fwrite($this->_testfile, "<" . $this->_namespace . "Test Status=".'"failed">'."\n");
          if(strpos($message,"Text [error] detected at character") !== false)
            {
            $errormessage = $message;
            $message = "Fatals errors (cf build)";
            $errors = true;
            }
          elseif(strpos($message,"Text [Notice]") !== false)
            {
            $warningmessage = $message;
            $message = "Warnings (cf build)";
            $warnings = true;
            }
          $this->_paintTestInfo($message);
          fwrite($this->_testfile, $this->_getIndent(1));
          fwrite($this->_testfile, "</" . $this->_namespace . "Test>\n");
          }
        if(isset($errors))
          {
          $this->_extractError($errormessage);
          }
        if(isset($warnings))
          {
          $this->_extractWarning($warningmessage);
          }
      echo "... **** Failed\n";
    }


  /**
     *    Extract an error from a warning (useful with web test cases)
     *    @param string $message        Message to encode.
     *    @access protected
     */
  function _extractError($message)
  {
    $errors = explode('[String:',$message);
    for($i=1;$i<sizeof($errors);$i++)
      {
      $this->paintError(strstr($errors[$i],' '));
      }
  }

  /**
     *    Extract an error from a warning (useful with web test cases)
     *    @param string $message        Message to encode.
     *    @access protected
     */
  function _extractWarning($message)
  {
    $warnings = explode('Notice (',$message);
    for($i=1;$i<sizeof($warnings);$i++)
    {
      $this->paintWarning(strstr($warnings[$i],' '));
    }
  }

  /**
     *    Paints error as CDAsh XML format.
     *    @param string $message        Message to encode.
     *    @access public
     */
   function paintError($message) {
        $this->computeTimeExecution();
        $this->_teststatus = "Uncompleted";
        $this->_updateStatus($this->_teststatus);
        $this->_paintErrorInfo($message);
    }


  /**
     *    Paints warnings as CDAsh XML format.
     *    @param string $message        Message to encode.
     *    @access public
     */
   function paintWarning($message) {
      $this->computeTimeExecution();
      $this->_teststatus = "Uncompleted";
      $this->_updateStatus($this->_teststatus);
      $this->_paintWarningInfo($message);
   }

  /**
     *    Paints errors information
     *    @param string $message        Message to encode.
     *    @access protected
     */
  function _paintErrorInfo($message)
    {
    if (!is_null($this->_testfile))
      {
      fwrite($this->_buildfile, $this->_getIndent(1));
      fwrite($this->_buildfile, "<" . $this->_namespace . "Error>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      $errorpos = strrpos($message,"in [");
      $errorline = substr($message,$errorpos+strlen("in ["),-1);
      fwrite($this->_buildfile, "<" . $this->_namespace . "BuildLogLine>$errorline</BuildLogLine>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      fwrite($this->_buildfile, "<" . $this->_namespace . "Text>".$this->toParsedXml($message)."</Text>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      fwrite($this->_buildfile, "<" . $this->_namespace . "PreContext></PreContext>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      fwrite($this->_buildfile, "<" . $this->_namespace . "PostContext></PostContext>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      fwrite($this->_buildfile, "<" . $this->_namespace . "RepeatCount>0</RepeatCount>\n");
      fwrite($this->_buildfile, $this->_getIndent(1));
      fwrite($this->_buildfile, "</" . $this->_namespace . "Error>\n");
      }
    }


  /**
      *    Paints warnings information
      *    @param string $message        Message to encode.
      *    @access protected
      */
    function _paintWarningInfo($message)
    {
      if (!is_null($this->_testfile))
      {
        fwrite($this->_buildfile, $this->_getIndent(1));
        fwrite($this->_buildfile, "<" . $this->_namespace . "Warning>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        $errorpos = strrpos($message,"at [");
        $errorline = substr($message,$errorpos+strlen("at ["),-1);
        fwrite($this->_buildfile, "<" . $this->_namespace . "BuildLogLine>$errorline</BuildLogLine>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "Text>".$this->toParsedXml($message)."</Text>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "PreContext></PreContext>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "PostContext></PostContext>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "RepeatCount>0</RepeatCount>\n");
        fwrite($this->_buildfile, $this->_getIndent(1));
        fwrite($this->_buildfile, "</" . $this->_namespace . "Warning>\n");
      }
    }

  /**
     *    Paints exception as CDAsh XML format.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintException($exception) {
        $this->computeTimeExecution();
        $this->_teststatus = "Uncompleted";
        $this->_updateStatus($this->_teststatus);
        $this->_paintExceptionInfo($exception);
    }

  /**
     *    Paints errors information
     *    @param string $message        Message to encode.
     *    @access protected
     */
    function _paintExceptionInfo()
      {
      if (!is_null($this->_testfile))
        {
        fwrite($this->_buildfile, $this->_getIndent(1));
        fwrite($this->_buildfile, "<" . $this->_namespace . "Warning>");
        $message = 'Unexpected exception of type [' . get_class($exception) .
                '] with message ['. $exception->getMessage() .
                '] in ['. $exception->getFile() .
                ' line ' . $exception->getLine() . ']';
        fwrite($this->_buildfile, "<" . $this->_namespace . "BuildLogLine>".$exception->getLine()."</BuildLogLine>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "Text>".$this->toParsedXml($message)."</Text>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "PreContext></PreContext>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "PostContext></PostContext>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "RepeatCount>0</RepeatCount>\n");
        fwrite($this->_buildfile, $this->_getIndent(1));
        fwrite($this->_buildfile, "</" . $this->_namespace . "Warning\n");
        }
      }

  /**
     *    print all the information related to the test
     *    @param message $output = output of the test
     *    @access protected
     */
  function _paintTestInfo($output)
    {
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "<" . $this->_namespace . "Name>".$this->_testname.
            "</Name>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    $fullPath = $this->_testpath[$this->_testN];
    $path = dirname($fullPath);
    fwrite($this->_testfile, "<" . $this->_namespace . "Path>".$path."</Path>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "<" . $this->_namespace . "FullName>".$fullPath."</FullName>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "<" . $this->_namespace . "FullCommandLine>php5 ".realpath('./AllTest.php').  "</FullCommandLine>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "<" . $this->_namespace . "Results>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "NamedMeasurement type=".'"numeric/double"'." name=".'"Execution Time"><Value>'.$this->_execution_time."</Value></NamedMeasurement>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "NamedMeasurement type=".'"text/string"'." name=".'"Completion Status"><Value>'.$this->_teststatus."</Value></NamedMeasurement>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "NamedMeasurement type=".'"text/string"'." name=".'"Command Line"><Value>php5 '.realpath('./AllTest.php')."</Value></NamedMeasurement>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "Measurement>\n");
    fwrite($this->_testfile, $this->_getIndent(4));
    fwrite($this->_testfile, "<" . $this->_namespace . "Value>".$this->toParsedXml($output)."</Value>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "/Measurement>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "</" . $this->_namespace . "Results>\n");
    }

  /**
     *    Update the last status to the new status
     *    @param message $newstatus = status to update
     *    @access protected
     */
  function _updateStatus($newstatus)
    {
    $beforeteststatus = '<Test Status="';
    $afterteststatus  = '<Name>';
    $toappend         = 'NotRun"'.">\n".$this->_getIndent(2);
    $filename         =  $this->_configure['outputdirectory'].'/Test.xml';
    $contents         = '';
    rewind($this->_testfile);
    while(!feof($this->_testfile))
      {
      $contents .= fgets($this->_testfile);
      }
    // We replace the test status
    $contentsbeforestatus = substr($contents,0,strrpos($contents,$beforeteststatus)+strlen($beforeteststatus));
    $contentsafterstatus  = substr($contents,strrpos($contents,$afterteststatus));
    $contents = $contentsbeforestatus.$toappend.$contentsafterstatus;
    rewind($this->_testfile);
    fwrite($this->_testfile,$contents);
    }


  /**
     *    paintEndCDashTest
     *    print the common end of all xml cdash file
     */
  function paintEndCDashTest()
    {
    fwrite($this->_testfile, $this->_getIndent(1));
    fwrite($this->_testfile, "<EndDateTime>".date("M d G:i T")."</EndDateTime>\n");
    fwrite($this->_testfile, $this->_getIndent(1));
    fwrite($this->_testfile, "<EndTestTime>".time()."</EndTestTime>\n");
    fwrite($this->_buildfile, $this->_getIndent(1));
    fwrite($this->_buildfile, "<EndDateTime>".date("M d G:i T")."</EndDateTime>\n");
    fwrite($this->_buildfile, "<EndBuildTime>".time()."</EndBuildTime>\n");
    fwrite($this->_testfile, $this->_getIndent(1));
    $elapsedMinutes = round($this->_elapsedminutes / 60 , 3);
    fwrite($this->_testfile, "<ElapsedMinutes>".$elapsedMinutes."</ElapsedMinutes>\n");
    fwrite($this->_buildfile, "<ElapsedMinutes>".$elapsedMinutes."</ElapsedMinutes>\n");
    fwrite($this->_testfile, "</Testing>\n");
    fwrite($this->_buildfile, "</Build>\n");
    fwrite($this->_testfile, "</Site>\n");
    fwrite($this->_buildfile, "</Site>\n");
    }

  /**
     *    getTestFunction
     *    return all the test funtion contained in the test file name
     */
  function getTestFunction($testFileName)
    {
    include_once "$testFileName";
    $testFile = fopen($testFileName,'r');
    $content  = fread($testFile,filesize($testFileName));
    fclose($testFile);
    $str = explode("class ",$content);
    $haystack = $str[1];
    $str = explode(" extends",$haystack);
    $obj = $str[0];
    $testcase = new $obj();
    $methods = $testcase->getTests();
    $testFunction = array();
    foreach($methods as $method)
      {
      if(strcmp($method,'start') != 0 &&
         strcmp($method,'startCase') != 0 &&
         strcmp($method,'endCase') != 0 &&
         strcmp($method,'end') != 0)
        {
        $testFunction[] = $method;
        }
      }
    return $testFunction;
    }


  /**
     *    computeTimeExecution
     *    compute the time execution
     */
  function computeTimeExecution()
  {
    $now = (float) array_sum(explode(' ',microtime()));
    $this->_execution_time = $now - $this->_start_method_time;
    $this->_elapsedminutes += $this->_execution_time;
  }
}
?>
