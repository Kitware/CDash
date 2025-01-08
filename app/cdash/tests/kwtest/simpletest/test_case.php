<?php

/**
 *  Base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 * Includes SimpleTest files and defined the root constant
 * for dependent libraries.
 */
require_once dirname(__FILE__) . '/invoker.php';
require_once dirname(__FILE__) . '/errors.php';
require_once dirname(__FILE__) . '/compatibility.php';
require_once dirname(__FILE__) . '/scorer.php';
require_once dirname(__FILE__) . '/expectation.php';
require_once dirname(__FILE__) . '/dumper.php';
require_once dirname(__FILE__) . '/simpletest.php';
require_once dirname(__FILE__) . '/exceptions.php';
require_once dirname(__FILE__) . '/reflection_php5.php';
/**#@-*/
if (!defined('SIMPLE_TEST')) {
    /*
     * @ignore
     */
    define('SIMPLE_TEST', dirname(__FILE__) . DIRECTORY_SEPARATOR);
}

/**
 *    Basic test case. This is the smallest unit of a test
 *    suite. It searches for
 *    all methods that start with the the string "test" and
 *    runs them. Working test cases extend this class.
 */
class SimpleTestCase
{
    private $label = false;
    protected $reporter;
    private $observers;
    private $should_skip = false;

    /**
     *    Sets up the test with no display.
     *
     * @param string $label if no test name is given then
     *                      the class name is used
     */
    public function __construct($label = false)
    {
        if ($label) {
            $this->label = $label;
        }
    }

    /**
     *    Accessor for the test name for subclasses.
     *
     * @return string name of the test
     */
    public function getLabel()
    {
        return $this->label ? $this->label : get_class($this);
    }

    /**
     *    This is a placeholder for skipping tests. In this
     *    method you place skipIf() and skipUnless() calls to
     *    set the skipping state.
     */
    public function skip()
    {
    }

    /**
     *    Will issue a message to the reporter and tell the test
     *    case to skip if the incoming flag is true.
     *
     * @param string $should_skip condition causing the tests to be skipped
     * @param string $message text of skip condition
     */
    public function skipIf($should_skip, $message = '%s')
    {
        if ($should_skip && !$this->should_skip) {
            $this->should_skip = true;
            $message = sprintf($message, 'Skipping [' . get_class($this) . ']');
            $this->reporter->paintSkip($message . $this->getAssertionLine());
        }
    }

    /**
     *    Accessor for the private variable $_shoud_skip
     */
    public function shouldSkip()
    {
        return $this->should_skip;
    }

    /**
     *    Will issue a message to the reporter and tell the test
     *    case to skip if the incoming flag is false.
     *
     * @param string $shouldnt_skip condition causing the tests to be run
     * @param string $message text of skip condition
     */
    public function skipUnless($shouldnt_skip, $message = false)
    {
        $this->skipIf(!$shouldnt_skip, $message);
    }

    /**
     *    Used to invoke the single tests.
     *
     * @return SimpleInvoker individual test runner
     */
    public function createInvoker()
    {
        return new SimpleErrorTrappingInvoker(
            new SimpleExceptionTrappingInvoker(new SimpleInvoker($this)));
    }

    /**
     *    Uses reflection to run every method within itself
     *    starting with the string "test" unless a method
     *    is specified.
     *
     * @param SimpleReporter $reporter current test reporter
     *
     * @return bool true if all tests passed
     */
    public function run($reporter)
    {
        $context = SimpleTest::getContext();
        $context->setTest($this);
        $context->setReporter($reporter);
        $this->reporter = $reporter;
        $started = false;
        foreach ($this->getTests() as $method) {
            if ($reporter->shouldInvoke($this->getLabel(), $method)) {
                $this->skip();
                if ($this->should_skip) {
                    break;
                }
                if (!$started) {
                    $reporter->paintCaseStart($this->getLabel());
                    $started = true;
                }
                $invoker = $this->reporter->createInvoker($this->createInvoker());
                $invoker->before($method);
                $invoker->invoke($method);
                $invoker->after($method);
            }
        }
        if ($started) {
            $reporter->paintCaseEnd($this->getLabel());
        }
        unset($this->reporter);
        $context->setTest(null);
        return $reporter->getStatus();
    }

    /**
     *    Gets a list of test names. Normally that will
     *    be all internal methods that start with the
     *    name "test". This method should be overridden
     *    if you want a different rule.
     *
     * @return array list of test names
     */
    public function getTests()
    {
        $methods = [];
        foreach (get_class_methods(get_class($this)) as $method) {
            if ($this->isTest($method)) {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    /**
     *    Tests to see if the method is a test that should
     *    be run. Currently any method that starts with 'test'
     *    is a candidate unless it is the constructor.
     *
     * @param string $method method name to try
     *
     * @return bool true if test method
     */
    protected function isTest($method)
    {
        if (strtolower(substr($method, 0, 4)) == 'test') {
            return !SimpleTestCompatibility::isA($this, strtolower($method));
        }
        return false;
    }

    /**
     *    Announces the start of the test.
     *
     * @param string $method test method just started
     */
    public function before($method)
    {
        $this->reporter->paintMethodStart($method);
        $this->observers = [];
    }

    /**
     *    Sets up unit test wide variables at the start
     *    of each test method. To be overridden in
     *    actual user test cases.
     */
    public function setUp()
    {
    }

    /**
     *    Clears the data set in the setUp() method call.
     *    To be overridden by the user in actual user test cases.
     */
    public function tearDown()
    {
    }

    /**
     *    Announces the end of the test. Includes private clean up.
     *
     * @param string $method test method just finished
     */
    public function after($method)
    {
        for ($i = 0; $i < count($this->observers); $i++) {
            $this->observers[$i]->atTestEnd($method, $this);
        }
        $this->reporter->paintMethodEnd($method);
    }

    /**
     *    Sets up an observer for the test end.
     *
     * @param object $observer must have atTestEnd()
     *                         method
     */
    public function tell($observer)
    {
        $this->observers[] = &$observer;
    }

    /**
     * @deprecated
     */
    public function pass($message = 'Pass')
    {
        if (!isset($this->reporter)) {
            trigger_error('Can only make assertions within test methods');
        }
        $this->reporter->paintPass(
            $message . $this->getAssertionLine());
        return true;
    }

    /**
     *    Sends a fail event with a message.
     *
     * @param string $message message to send
     */
    public function fail($message = 'Fail')
    {
        if (!isset($this->reporter)) {
            trigger_error('Can only make assertions within test methods');
        }
        $this->reporter->paintFail(
            $message . $this->getAssertionLine());
        return false;
    }

    /**
     *    Formats a PHP error and dispatches it to the
     *    reporter.
     *
     * @param int $severity PHP error code
     * @param string $message text of error
     * @param string $file file error occoured in
     * @param int $line line number of error
     */
    public function error($severity, $message, $file, $line)
    {
        if (!isset($this->reporter)) {
            trigger_error('Can only make assertions within test methods');
        }
        $this->reporter->paintError(
            "Unexpected PHP error [$message] severity [$severity] in [$file line $line]");
    }

    /**
     *    Formats an exception and dispatches it to the
     *    reporter.
     *
     * @param Exception $exception object thrown
     */
    public function exception($exception)
    {
        $this->reporter->paintException($exception);
    }

    /**
     *    For user defined expansion of the available messages.
     *
     * @param string $type tag for sorting the signals
     * @param mixed $payload extra user specific information
     */
    public function signal($type, $payload)
    {
        if (!isset($this->reporter)) {
            trigger_error('Can only make assertions within test methods');
        }
        $this->reporter->paintSignal($type, $payload);
    }

    /**
     *    Runs an expectation directly, for extending the
     *    tests with new expectation classes.
     *
     * @param SimpleExpectation $expectation expectation subclass
     * @param mixed $compare value to compare
     * @param string $message message to display
     *
     * @return bool True on pass
     */
    public function assert($expectation, $compare, $message = '%s')
    {
        if ($expectation->test($compare)) {
            return $this->pass(sprintf(
                $message,
                $expectation->overlayMessage($compare, $this->reporter->getDumper())));
        } else {
            return $this->fail(sprintf(
                $message,
                $expectation->overlayMessage($compare, $this->reporter->getDumper())));
        }
    }

    /**
     *    Uses a stack trace to find the line of an assertion.
     *
     * @return string line number of first assert*
     *                method embedded in format string
     */
    public function getAssertionLine()
    {
        $trace = new SimpleStackTrace(['assert', 'expect', 'pass', 'fail', 'skip']);
        return $trace->traceMethod();
    }

    /**
     *    Sends a formatted dump of a variable to the
     *    test suite for those emergency debugging
     *    situations.
     *
     * @param mixed $variable variable to display
     * @param string $message message to display
     *
     * @return mixed the original variable
     */
    public function dump($variable, $message = false)
    {
        $dumper = $this->reporter->getDumper();
        $formatted = $dumper->dump($variable);
        if ($message) {
            $formatted = $message . "\n" . $formatted;
        }
        $this->reporter->paintFormattedMessage($formatted);
        return $variable;
    }

    /**
     *    Accessor for the number of subtests including myelf.
     *
     * @return int number of test cases
     */
    public function getSize()
    {
        return 1;
    }
}

/**
 *  Helps to extract test cases automatically from a file.
 */
class SimpleFileLoader
{
    /**
     *    Builds a test suite from a library of test cases.
     *    The new suite is composed into this one.
     *
     * @param string $test_file file name of library with
     *                          test case classes
     *
     * @return TestSuite the new test suite
     */
    public function load($test_file)
    {
        $existing_classes = get_declared_classes();
        $existing_globals = get_defined_vars();
        include_once $test_file;
        $new_globals = get_defined_vars();
        $this->makeFileVariablesGlobal($existing_globals, $new_globals);
        $new_classes = array_diff(get_declared_classes(), $existing_classes);
        if (empty($new_classes)) {
            $new_classes = $this->scrapeClassesFromFile($test_file);
        }
        $classes = $this->selectRunnableTests($new_classes);
        return $this->createSuiteFromClasses($test_file, $classes);
    }

    /**
     *    Imports new variables into the global namespace.
     *
     * @param hash $existing variables before the file was loaded
     * @param hash $new variables after the file was loaded
     */
    protected function makeFileVariablesGlobal($existing, $new)
    {
        $globals = array_diff(array_keys($new), array_keys($existing));
        foreach ($globals as $global) {
            $GLOBALS[$global] = $new[$global];
        }
    }

    /**
     *    Lookup classnames from file contents, in case the
     *    file may have been included before.
     *    Note: This is probably too clever by half. Figuring this
     *    out after a failed test case is going to be tricky for us,
     *    never mind the user. A test case should not be included
     *    twice anyway.
     *
     * @param string $test_file file name with classes
     */
    protected function scrapeClassesFromFile($test_file)
    {
        preg_match_all('~^\s*class\s+(\w+)(\s+(extends|implements)\s+\w+)*\s*\{~mi',
            file_get_contents($test_file),
            $matches);
        return $matches[1];
    }

    /**
     *    Calculates the incoming test cases. Skips abstract
     *    and ignored classes.
     *
     * @param array $candidates candidate classes
     *
     * @return array new classes which are test
     *               cases that shouldn't be ignored
     */
    public function selectRunnableTests($candidates)
    {
        $classes = [];
        foreach ($candidates as $class) {
            if (TestSuite::getBaseTestCase($class)) {
                $reflection = new SimpleReflection($class);
                if ($reflection->isAbstract()) {
                    SimpleTest::ignore($class);
                } else {
                    $classes[] = $class;
                }
            }
        }
        return $classes;
    }

    /**
     *    Builds a test suite from a class list.
     *
     * @param string $title title of new group
     * @param array $classes test classes
     *
     * @return TestSuite group loaded with the new
     *                   test cases
     */
    public function createSuiteFromClasses($title, $classes)
    {
        if (count($classes) == 0) {
            $suite = new BadTestSuite($title, "No runnable test cases in [$title]");
            return $suite;
        }
        SimpleTest::ignoreParentsIfIgnored($classes);
        $suite = new TestSuite($title);
        foreach ($classes as $class) {
            if (!SimpleTest::isIgnored($class)) {
                $suite->add($class);
            }
        }
        return $suite;
    }
}

/**
 *    This is a composite test class for combining
 *    test cases and other RunnableTest classes into
 *    a group test.
 */
class TestSuite
{
    private $label;
    private $test_cases;

    /**
     *    Sets the name of the test suite.
     *
     * @param string $label name sent at the start and end
     *                      of the test
     */
    public function __construct($label = false)
    {
        $this->label = $label;
        $this->test_cases = [];
    }

    /**
     *    Accessor for the test name for subclasses. If the suite
     *    wraps a single test case the label defaults to the name of that test.
     *
     * @return string name of the test
     */
    public function getLabel()
    {
        if (!$this->label) {
            return ($this->getSize() == 1) ?
                get_class($this->test_cases[0]) : get_class($this);
        } else {
            return $this->label;
        }
    }

    /**
     *    Adds a test into the suite by instance or class. The class will
     *    be instantiated if it's a test suite.
     *
     * @param SimpleTestCase $test_case suite or individual test
     *                                  case implementing the
     *                                  runnable test interface
     */
    public function add($test_case)
    {
        if (!is_string($test_case)) {
            $this->test_cases[] = $test_case;
        } elseif (TestSuite::getBaseTestCase($test_case) == 'testsuite') {
            $this->test_cases[] = new $test_case();
        } else {
            $this->test_cases[] = $test_case;
        }
    }

    /**
     *    Builds a test suite from a library of test cases.
     *    The new suite is composed into this one.
     *
     * @param string $test_file file name of library with
     *                          test case classes
     */
    public function addFile($test_file)
    {
        $extractor = new SimpleFileLoader();
        $this->add($extractor->load($test_file));
    }

    /**
     *    Delegates to a visiting collector to add test
     *    files.
     *
     * @param string $path path to scan from
     * @param SimpleCollector $collector directory scanner
     */
    public function collect($path, $collector)
    {
        $collector->collect($this, $path);
    }

    /**
     *    Invokes run() on all of the held test cases, instantiating
     *    them if necessary.
     *
     * @param SimpleReporter $reporter current test reporter
     */
    public function run($reporter)
    {
        $reporter->paintGroupStart($this->getLabel(), $this->getSize());
        for ($i = 0, $count = count($this->test_cases); $i < $count; $i++) {
            if (is_string($this->test_cases[$i])) {
                $class = $this->test_cases[$i];
                $test = new $class();
                $test->run($reporter);
                unset($test);
            } else {
                $this->test_cases[$i]->run($reporter);
            }
        }
        $reporter->paintGroupEnd($this->getLabel());
        return $reporter->getStatus();
    }

    /**
     *    Number of contained test cases.
     *
     * @return int total count of cases in the group
     */
    public function getSize()
    {
        $count = 0;
        foreach ($this->test_cases as $case) {
            if (is_string($case)) {
                if (!SimpleTest::isIgnored($case)) {
                    $count++;
                }
            } else {
                $count += $case->getSize();
            }
        }
        return $count;
    }

    /**
     *    Test to see if a class is derived from the
     *    SimpleTestCase class.
     *
     * @param string $class class name
     */
    public static function getBaseTestCase($class)
    {
        while ($class = get_parent_class($class)) {
            $class = strtolower($class);
            if ($class == 'simpletestcase' || $class == 'testsuite') {
                return $class;
            }
        }
        return false;
    }
}

/**
 *    This is a failing group test for when a test suite hasn't
 *    loaded properly.
 */
class BadTestSuite
{
    private $label;
    private $error;

    /**
     *    Sets the name of the test suite and error message.
     *
     * @param string $label name sent at the start and end
     *                      of the test
     */
    public function __construct($label, $error)
    {
        $this->label = $label;
        $this->error = $error;
    }

    /**
     *    Accessor for the test name for subclasses.
     *
     * @return string name of the test
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     *    Sends a single error to the reporter.
     *
     * @param SimpleReporter $reporter current test reporter
     */
    public function run($reporter)
    {
        $reporter->paintGroupStart($this->getLabel(), $this->getSize());
        $reporter->paintFail('Bad TestSuite [' . $this->getLabel() .
            '] with error [' . $this->error . ']');
        $reporter->paintGroupEnd($this->getLabel());
        return $reporter->getStatus();
    }

    /**
     *    Number of contained test cases. Always zero.
     *
     * @return int total count of cases in the group
     */
    public function getSize()
    {
        return 0;
    }
}
