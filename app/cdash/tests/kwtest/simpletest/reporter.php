<?php

/**
 *  base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/scorer.php';
// require_once(dirname(__FILE__) . '/arguments.php');
/**#@-*/

/**
 *    Sample minimal test displayer. Generates only
 *    failure messages and a pass count.
 */
class HtmlReporter extends SimpleReporter
{
    private $character_set;

    /**
     *    Does nothing yet. The first output will
     *    be sent on the first test start. For use
     *    by a web browser.
     */
    public function __construct($character_set = 'ISO-8859-1')
    {
        parent::__construct();
        $this->character_set = $character_set;
    }

    /**
     *    Paints the top of the web page setting the
     *    title to the name of the starting test.
     *
     * @param string $test_name name class of test
     */
    public function paintHeader($test_name)
    {
        $this->sendNoCacheHeaders();
        echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';
        echo "<html>\n<head>\n<title>$test_name</title>\n";
        echo '<meta http-equiv="Content-Type" content="text/html; charset=' .
            $this->character_set . "\">\n";
        echo "<style type=\"text/css\">\n";
        echo $this->getCss() . "\n";
        echo "</style>\n";
        echo "</head>\n<body>\n";
        echo "<h1>$test_name</h1>\n";
        flush();
    }

    /**
     *    Send the headers necessary to ensure the page is
     *    reloaded on every request. Otherwise you could be
     *    scratching your head over out of date test data.
     */
    public static function sendNoCacheHeaders()
    {
        if (!headers_sent()) {
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
        }
    }

    /**
     *    Paints the CSS. Add additional styles here.
     *
     * @return string CSS code as text
     */
    protected function getCss()
    {
        return '.fail { background-color: inherit; color: red; }' .
        '.pass { background-color: inherit; color: green; }' .
        ' pre { background-color: lightgray; color: inherit; }';
    }

    /**
     *    Paints the end of the test with a summary of
     *    the passes and failures.
     *
     * @param string $test_name name class of test
     */
    public function paintFooter($test_name)
    {
        $colour = ($this->getFailCount() + $this->getExceptionCount() > 0 ? 'red' : 'green');
        echo '<div style="';
        echo "padding: 8px; margin-top: 1em; background-color: $colour; color: white;";
        echo '">';
        echo $this->getTestCaseProgress() . '/' . $this->getTestCaseCount();
        echo " test cases complete:\n";
        echo '<strong>' . $this->getPassCount() . '</strong> passes, ';
        echo '<strong>' . $this->getFailCount() . '</strong> fails and ';
        echo '<strong>' . $this->getExceptionCount() . '</strong> exceptions.';
        echo "</div>\n";
        echo "</body>\n</html>\n";
    }

    /**
     *    Paints the test failure with a breadcrumbs
     *    trail of the nesting test suites below the
     *    top level test.
     *
     * @param string $message failure message displayed in
     *                        the context of the other tests
     */
    public function paintFail($message)
    {
        parent::paintFail($message);
        echo '<span class="fail">Fail</span>: ';
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        echo implode(' -&gt; ', $breadcrumb);
        echo ' -&gt; ' . $this->htmlEntities($message) . "<br />\n";
    }

    /**
     *    Paints a PHP error.
     *
     * @param string $message message is ignored
     */
    public function paintError($message)
    {
        parent::paintError($message);
        echo '<span class="fail">Exception</span>: ';
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        echo implode(' -&gt; ', $breadcrumb);
        echo ' -&gt; <strong>' . $this->htmlEntities($message) . "</strong><br />\n";
    }

    /**
     *    Paints a PHP exception.
     *
     * @param Exception $exception exception to display
     */
    public function paintException($exception)
    {
        parent::paintException($exception);
        echo '<span class="fail">Exception</span>: ';
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        echo implode(' -&gt; ', $breadcrumb);
        $message = 'Unexpected exception of type [' . get_class($exception) .
            '] with message [' . $exception->getMessage() .
            '] in [' . $exception->getFile() .
            ' line ' . $exception->getLine() . ']';
        echo ' -&gt; <strong>' . $this->htmlEntities($message) . "</strong><br />\n";
    }

    /**
     *    Prints the message for skipping tests.
     *
     * @param string $message text of skip condition
     */
    public function paintSkip($message)
    {
        parent::paintSkip($message);
        echo '<span class="pass">Skipped</span>: ';
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        echo implode(' -&gt; ', $breadcrumb);
        echo ' -&gt; ' . $this->htmlEntities($message) . "<br />\n";
    }

    /**
     *    Paints formatted text such as dumped privateiables.
     *
     * @param string $message text to show
     */
    public function paintFormattedMessage($message)
    {
        echo '<pre>' . $this->htmlEntities($message) . '</pre>';
    }

    /**
     *    Character set adjusted entity conversion.
     *
     * @param string $message plain text or Unicode message
     *
     * @return string browser readable message
     */
    protected function htmlEntities($message)
    {
        return htmlentities($message, ENT_COMPAT, $this->character_set);
    }
}

/**
 *    Sample minimal test displayer. Generates only
 *    failure messages and a pass count. For command
 *    line use. I've tried to make it look like JUnit,
 *    but I wanted to output the errors as they arrived
 *    which meant dropping the dots.
 */
class TextReporter extends SimpleReporter
{
    /**
     *    Does nothing yet. The first output will
     *    be sent on the first test start.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     *    Paints the title only.
     *
     * @param string $test_name name class of test
     */
    public function paintHeader($test_name)
    {
        if (!SimpleReporter::inCli()) {
            header('Content-type: text/plain');
        }
        echo "$test_name\n";
        flush();
    }

    /**
     *    Paints the end of the test with a summary of
     *    the passes and failures.
     *
     * @param string $test_name name class of test
     */
    public function paintFooter($test_name)
    {
        if ($this->getFailCount() + $this->getExceptionCount() == 0) {
            echo "OK\n";
        } else {
            echo "FAILURES!!!\n";
        }
        echo 'Test cases run: ' . $this->getTestCaseProgress() .
            '/' . $this->getTestCaseCount() .
            ', Passes: ' . $this->getPassCount() .
            ', Failures: ' . $this->getFailCount() .
            ', Exceptions: ' . $this->getExceptionCount() . "\n";
    }

    /**
     *    Paints the test failure as a stack trace.
     *
     * @param string $message failure message displayed in
     *                        the context of the other tests
     */
    public function paintFail($message)
    {
        parent::paintFail($message);
        echo $this->getFailCount() . ") $message\n";
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        echo "\tin " . implode("\n\tin ", array_reverse($breadcrumb));
        echo "\n";
    }

    /**
     *    Paints a PHP error or exception.
     *
     * @param string $message message to be shown
     *
     * @abstract
     */
    public function paintError($message)
    {
        parent::paintError($message);
        echo 'Exception ' . $this->getExceptionCount() . "!\n$message\n";
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        echo "\tin " . implode("\n\tin ", array_reverse($breadcrumb));
        echo "\n";
    }

    /**
     *    Paints a PHP error or exception.
     *
     * @param Exception $exception exception to describe
     *
     * @abstract
     */
    public function paintException($exception)
    {
        parent::paintException($exception);
        $message = 'Unexpected exception of type [' . get_class($exception) .
            '] with message [' . $exception->getMessage() .
            '] in [' . $exception->getFile() .
            ' line ' . $exception->getLine() . ']';
        echo 'Exception ' . $this->getExceptionCount() . "!\n$message\n";
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        echo "\tin " . implode("\n\tin ", array_reverse($breadcrumb));
        echo "\n";
    }

    /**
     *    Prints the message for skipping tests.
     *
     * @param string $message text of skip condition
     */
    public function paintSkip($message)
    {
        parent::paintSkip($message);
        echo "Skip: $message\n";
    }

    /**
     *    Paints formatted text such as dumped privateiables.
     *
     * @param string $message text to show
     */
    public function paintFormattedMessage($message)
    {
        echo "$message\n";
        flush();
    }
}

/**
 *    Runs just a single test group, a single case or
 *    even a single test within that case.
 */
class SelectiveReporter extends SimpleReporterDecorator
{
    private $just_this_case = false;
    private $just_this_test = false;
    private $on;

    /**
     *    Selects the test case or group to be run,
     *    and optionally a specific test.
     *
     * @param SimpleScorer $reporter reporter to receive events
     * @param string $just_this_case only this case or group will run
     * @param string $just_this_test only this test method will run
     */
    public function __construct($reporter, $just_this_case = false, $just_this_test = false)
    {
        if (isset($just_this_case) && $just_this_case) {
            $this->just_this_case = strtolower($just_this_case);
            $this->off();
        } else {
            $this->on();
        }
        if (isset($just_this_test) && $just_this_test) {
            $this->just_this_test = strtolower($just_this_test);
        }
        parent::__construct($reporter);
    }

    /**
     *    Compares criteria to actual the case/group name.
     *
     * @param string $test_case the incoming test
     *
     * @return bool true if matched
     */
    protected function matchesTestCase($test_case)
    {
        return $this->just_this_case == strtolower($test_case);
    }

    /**
     *    Compares criteria to actual the test name. If no
     *    name was specified at the beginning, then all tests
     *    can run.
     *
     * @param string $method the incoming test method
     *
     * @return bool true if matched
     */
    protected function shouldRunTest($test_case, $method)
    {
        if ($this->isOn() || $this->matchesTestCase($test_case)) {
            if ($this->just_this_test) {
                return $this->just_this_test == strtolower($method);
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     *    Switch on testing for the group or subgroup.
     */
    protected function on()
    {
        $this->on = true;
    }

    /**
     *    Switch off testing for the group or subgroup.
     */
    protected function off()
    {
        $this->on = false;
    }

    /**
     *    Is this group actually being tested?
     *
     * @return bool true if the current test group is active
     */
    protected function isOn()
    {
        return $this->on;
    }

    /**
     *    Veto everything that doesn't match the method wanted.
     *
     * @param string $test_case name of test case
     * @param string $method name of test method
     *
     * @return bool true if test should be run
     */
    public function shouldInvoke($test_case, $method)
    {
        if ($this->shouldRunTest($test_case, $method)) {
            return $this->reporter->shouldInvoke($test_case, $method);
        }
        return false;
    }

    /**
     *    Paints the start of a group test.
     *
     * @param string $test_case name of test or other label
     * @param int $size number of test cases starting
     */
    public function paintGroupStart($test_case, $size)
    {
        if ($this->just_this_case && $this->matchesTestCase($test_case)) {
            $this->on();
        }
        $this->reporter->paintGroupStart($test_case, $size);
    }

    /**
     *    Paints the end of a group test.
     *
     * @param string $test_case name of test or other label
     */
    public function paintGroupEnd($test_case)
    {
        $this->reporter->paintGroupEnd($test_case);
        if ($this->just_this_case && $this->matchesTestCase($test_case)) {
            $this->off();
        }
    }
}

/**
 *    Suppresses skip messages.
 */
class NoSkipsReporter extends SimpleReporterDecorator
{
    /**
     *    Does nothing.
     *
     * @param string $message text of skip condition
     */
    public function paintSkip($message)
    {
    }
}
