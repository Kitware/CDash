<?php

/**
 *  base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 * include SimpleTest files
 */
require_once dirname(__FILE__) . '/expectation.php';
require_once dirname(__FILE__) . '/simpletest.php';
require_once dirname(__FILE__) . '/dumper.php';
require_once dirname(__FILE__) . '/reflection_php5.php';
/**#@-*/

/*
 * Default character simpletest will substitute for any value
 */
if (!defined('MOCK_ANYTHING')) {
    define('MOCK_ANYTHING', '*');
}

/**
 *    Parameter comparison assertion.
 */
class ParametersExpectation extends SimpleExpectation
{
    private $expected;

    /**
     *    Sets the expected parameter list.
     *
     * @param string $message customised message on failure
     */
    public function __construct($expected = false, $message = '%s')
    {
        parent::__construct($message);
        $this->expected = $expected;
    }

    /**
     *    Tests the assertion. True if correct.
     *
     * @param array $parameters comparison values
     *
     * @return bool true if correct
     */
    public function test($parameters)
    {
        if (!is_array($this->expected)) {
            return true;
        }
        if (count($this->expected) != count($parameters)) {
            return false;
        }
        for ($i = 0; $i < count($this->expected); $i++) {
            if (!$this->testParameter($parameters[$i], $this->expected[$i])) {
                return false;
            }
        }
        return true;
    }

    /**
     *    Tests an individual parameter.
     *
     * @param mixed $parameter value to test
     * @param mixed $expected comparison value
     *
     * @return bool true if expectation
     *              fulfilled
     */
    protected function testParameter($parameter, $expected)
    {
        $comparison = $this->coerceToExpectation($expected);
        return $comparison->test($parameter);
    }

    /**
     *    Returns a human readable test message.
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($parameters)
    {
        if ($this->test($parameters)) {
            return 'Expectation of ' . count($this->expected) .
            ' arguments of [' . $this->renderArguments($this->expected) .
            '] is correct';
        } else {
            return $this->describeDifference($this->expected, $parameters);
        }
    }

    /**
     *    Message to display if expectation differs from
     *    the parameters actually received.
     *
     * @param array $expected expected parameters as list
     * @param array $parameters actual parameters received
     *
     * @return string description of difference
     */
    protected function describeDifference($expected, $parameters)
    {
        if (count($expected) != count($parameters)) {
            return 'Expected ' . count($expected) .
            ' arguments of [' . $this->renderArguments($expected) .
            '] but got ' . count($parameters) .
            ' arguments of [' . $this->renderArguments($parameters) . ']';
        }
        $messages = [];
        for ($i = 0; $i < count($expected); $i++) {
            $comparison = $this->coerceToExpectation($expected[$i]);
            if (!$comparison->test($parameters[$i])) {
                $messages[] = 'parameter ' . ($i + 1) . ' with [' .
                    $comparison->overlayMessage($parameters[$i], $this->getDumper()) . ']';
            }
        }
        return 'Parameter expectation differs at ' . implode(' and ', $messages);
    }

    /**
     *    Creates an identical expectation if the
     *    object/value is not already some type
     *    of expectation.
     *
     * @param mixed $expected expected value
     *
     * @return SimpleExpectation expectation object
     */
    protected function coerceToExpectation($expected)
    {
        if (SimpleExpectation::isExpectation($expected)) {
            return $expected;
        }
        return new IdenticalExpectation($expected);
    }

    /**
     *    Renders the argument list as a string for
     *    messages.
     *
     * @param array $args incoming arguments
     *
     * @return string simple description of type and value
     */
    protected function renderArguments($args)
    {
        $descriptions = [];
        if (is_array($args)) {
            foreach ($args as $arg) {
                $dumper = new SimpleDumper();
                $descriptions[] = $dumper->describeValue($arg);
            }
        }
        return implode(', ', $descriptions);
    }
}

/**
 *    Confirms that the number of calls on a method is as expected.
 */
class CallCountExpectation extends SimpleExpectation
{
    private $method;
    private $count;

    /**
     *    Stashes the method and expected count for later
     *    reporting.
     *
     * @param string $method name of method to confirm against
     * @param int $count expected number of calls
     * @param string $message custom error message
     */
    public function __construct($method, $count, $message = '%s')
    {
        $this->method = $method;
        $this->count = $count;
        parent::__construct($message);
    }

    /**
     *    Tests the assertion. True if correct.
     *
     * @param int $compare measured call count
     *
     * @return bool true if expected
     */
    public function test($compare)
    {
        return $this->count == $compare;
    }

    /**
     *    Reports the comparison.
     *
     * @param int $compare measured call count
     *
     * @return string message to show
     */
    public function testMessage($compare)
    {
        return 'Expected call count for [' . $this->method .
        '] was [' . $this->count .
        '] got [' . $compare . ']';
    }
}

/**
 *    Confirms that the number of calls on a method is as expected.
 */
class MinimumCallCountExpectation extends SimpleExpectation
{
    private $method;
    private $count;

    /**
     *    Stashes the method and expected count for later
     *    reporting.
     *
     * @param string $method name of method to confirm against
     * @param int $count minimum number of calls
     * @param string $message custom error message
     */
    public function __construct($method, $count, $message = '%s')
    {
        $this->method = $method;
        $this->count = $count;
        parent::__construct($message);
    }

    /**
     *    Tests the assertion. True if correct.
     *
     * @param int $compare measured call count
     *
     * @return bool true if enough
     */
    public function test($compare)
    {
        return $this->count <= $compare;
    }

    /**
     *    Reports the comparison.
     *
     * @param int $compare measured call count
     *
     * @return string message to show
     */
    public function testMessage($compare)
    {
        return 'Minimum call count for [' . $this->method .
        '] was [' . $this->count .
        '] got [' . $compare . ']';
    }
}

/**
 *    Confirms that the number of calls on a method is as expected.
 */
class MaximumCallCountExpectation extends SimpleExpectation
{
    private $method;
    private $count;

    /**
     *    Stashes the method and expected count for later
     *    reporting.
     *
     * @param string $method name of method to confirm against
     * @param int $count minimum number of calls
     * @param string $message custom error message
     */
    public function __construct($method, $count, $message = '%s')
    {
        $this->method = $method;
        $this->count = $count;
        parent::__construct($message);
    }

    /**
     *    Tests the assertion. True if correct.
     *
     * @param int $compare measured call count
     *
     * @return bool true if not over
     */
    public function test($compare)
    {
        return $this->count >= $compare;
    }

    /**
     *    Reports the comparison.
     *
     * @param int $compare measured call count
     *
     * @return string message to show
     */
    public function testMessage($compare)
    {
        return 'Maximum call count for [' . $this->method .
        '] was [' . $this->count .
        '] got [' . $compare . ']';
    }
}

/**
 *    Retrieves method actions by searching the
 *    parameter lists until an expected match is found.
 */
class SimpleSignatureMap
{
    private $map;

    /**
     *    Creates an empty call map.
     */
    public function __construct()
    {
        $this->map = [];
    }

    /**
     *    Stashes a reference against a method call.
     *
     * @param array $parameters array of arguments (including wildcards)
     * @param mixed $action reference placed in the map
     */
    public function add($parameters, $action)
    {
        $place = count($this->map);
        $this->map[$place] = [];
        $this->map[$place]['params'] = new ParametersExpectation($parameters);
        $this->map[$place]['content'] = $action;
    }

    /**
     *    Searches the call list for a matching parameter
     *    set. Returned by reference.
     *
     * @param array $parameters parameters to search by
     *                          without wildcards
     *
     * @return object object held in the first matching
     *                slot, otherwise null
     */
    public function &findFirstAction($parameters)
    {
        $slot = $this->findFirstSlot($parameters);
        if (isset($slot) && isset($slot['content'])) {
            return $slot['content'];
        }
        $null = null;
        return $null;
    }

    /**
     *    Searches the call list for a matching parameter
     *    set. True if successful.
     *
     * @param array $parameters parameters to search by
     *                          without wildcards
     *
     * @return bool true if a match is present
     */
    public function isMatch($parameters)
    {
        return $this->findFirstSlot($parameters) != null;
    }

    /**
     *    Compares the incoming parameters with the
     *    internal expectation. Uses the incoming $test
     *    to dispatch the test message.
     *
     * @param SimpleTestCase $test test to dispatch to
     * @param array $parameters the actual calling arguments
     * @param string $message the message to overlay
     */
    public function test($test, $parameters, $message)
    {
    }

    /**
     *    Searches the map for a matching item.
     *
     * @param array $parameters parameters to search by
     *                          without wildcards
     *
     * @return array reference to slot or null
     */
    public function &findFirstSlot($parameters)
    {
        $count = count($this->map);
        for ($i = 0; $i < $count; $i++) {
            if ($this->map[$i]['params']->test($parameters)) {
                return $this->map[$i];
            }
        }
        $null = null;
        return $null;
    }
}

/**
 *    Allows setting of actions against call signatures either
 *    at a specific time, or always. Specific time settings
 *    trump lasting ones, otherwise the most recently added
 *    will mask an earlier match.
 */
class SimpleCallSchedule
{
    private $wildcard = MOCK_ANYTHING;
    private $always;
    private $at;

    /**
     *    Sets up an empty response schedule.
     *    Creates an empty call map.
     */
    public function __construct()
    {
        $this->always = [];
        $this->at = [];
    }

    /**
     *    Stores an action against a signature that
     *    will always fire unless masked by a time
     *    specific one.
     *
     * @param string $method method name
     * @param array $args calling parameters
     * @param SimpleAction $action actually simpleByValue, etc
     */
    public function register($method, $args, $action)
    {
        $args = $this->replaceWildcards($args);
        $method = strtolower($method);
        if (!isset($this->always[$method])) {
            $this->always[$method] = new SimpleSignatureMap();
        }
        $this->always[$method]->add($args, $action);
    }

    /**
     *    Stores an action against a signature that
     *    will fire at a specific time in the future.
     *
     * @param int $step delay of calls to this method,
     *                  0 is next
     * @param string $method method name
     * @param array $args calling parameters
     * @param SimpleAction $action actually SimpleByValue, etc
     */
    public function registerAt($step, $method, $args, $action)
    {
        $args = $this->replaceWildcards($args);
        $method = strtolower($method);
        if (!isset($this->at[$method])) {
            $this->at[$method] = [];
        }
        if (!isset($this->at[$method][$step])) {
            $this->at[$method][$step] = new SimpleSignatureMap();
        }
        $this->at[$method][$step]->add($args, $action);
    }

    /**
     *  Sets up an expectation on the argument list.
     *
     * @param string $method method to test
     * @param array $args bare arguments or list of
     *                    expectation objects
     * @param string $message failure message
     */
    public function expectArguments($method, $args, $message)
    {
        $args = $this->replaceWildcards($args);
        $message .= Mock::getExpectationLine();
        $this->expected_args[strtolower($method)] =
            new ParametersExpectation($args, $message);
    }

    /**
     *    Actually carry out the action stored previously,
     *    if the parameters match.
     *
     * @param int $step time of call
     * @param string $method method name
     * @param array $args the parameters making up the
     *                    rest of the call
     *
     * @return mixed the result of the action
     */
    public function &respond($step, $method, $args)
    {
        $method = strtolower($method);
        if (isset($this->at[$method][$step])) {
            if ($this->at[$method][$step]->isMatch($args)) {
                $action = $this->at[$method][$step]->findFirstAction($args);
                if (isset($action)) {
                    return $action->act();
                }
            }
        }
        if (isset($this->always[$method])) {
            $action = $this->always[$method]->findFirstAction($args);
            if (isset($action)) {
                return $action->act();
            }
        }
        $null = null;
        return $null;
    }

    /**
     *    Replaces wildcard matches with wildcard
     *    expectations in the argument list.
     *
     * @param array $args raw argument list
     *
     * @return array argument list with
     *               expectations
     */
    protected function replaceWildcards($args)
    {
        if ($args === false) {
            return false;
        }
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === $this->wildcard) {
                $args[$i] = new AnythingExpectation();
            }
        }
        return $args;
    }
}

/**
 *    A type of SimpleMethodAction.
 *    Stashes a value for returning later. Follows usual
 *    PHP5 semantics of objects being returned by reference.
 */
class SimpleReturn
{
    private $value;

    /**
     *    Stashes it for later.
     *
     * @param mixed $value you need to clone objects
     *                     if you want copy semantics
     *                     for these
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     *    Returns the value stored earlier.
     *
     * @return mixed whatever was stashed
     */
    public function act()
    {
        return $this->value;
    }
}

/**
 *    A type of SimpleMethodAction.
 *    Stashes a reference for returning later.
 */
class SimpleByReference
{
    private $reference;

    /**
     *    Stashes it for later.
     *
     * @param mixed $reference actual PHP4 style reference
     */
    public function __construct(&$reference)
    {
        $this->reference = &$reference;
    }

    /**
     *    Returns the reference stored earlier.
     *
     * @return mixed whatever was stashed
     */
    public function &act()
    {
        return $this->reference;
    }
}

/**
 *    A type of SimpleMethodAction.
 *    Stashes a value for returning later.
 */
class SimpleByValue
{
    private $value;

    /**
     *    Stashes it for later.
     *
     * @param mixed $value you need to clone objects
     *                     if you want copy semantics
     *                     for these
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     *    Returns the value stored earlier.
     *
     * @return mixed whatever was stashed
     */
    public function &act()
    {
        $dummy = $this->value;
        return $dummy;
    }
}

/**
 *    A type of SimpleMethodAction.
 *    Stashes an exception for throwing later.
 */
class SimpleThrower
{
    private $exception;

    /**
     *    Stashes it for later.
     *
     * @param Exception $exception the exception object to throw
     */
    public function __construct($exception)
    {
        $this->exception = $exception;
    }

    /**
     *    Throws the exceptins stashed earlier.
     */
    public function act()
    {
        throw $this->exception;
    }
}

/**
 *    A type of SimpleMethodAction.
 *    Stashes an error for emitting later.
 */
class SimpleErrorThrower
{
    private $error;
    private $severity;

    /**
     *    Stashes an error to throw later.
     *
     * @param string $error error message
     * @param int $severity PHP error constant, e.g E_USER_ERROR.
     */
    public function __construct($error, $severity)
    {
        $this->error = $error;
        $this->severity = $severity;
    }

    /**
     *    Triggers the stashed error.
     */
    public function &act()
    {
        trigger_error($this->error, $this->severity);
        $null = null;
        return $null;
    }
}

/**
 *    A base class or delegate that extends an
 *    empty collection of methods that can have their
 *    return values set and expectations made of the
 *    calls upon them. The mock will assert the
 *    expectations against it's attached test case in
 *    addition to the server stub behaviour or returning
 *    preprogrammed responses.
 */
class SimpleMock
{
    private $actions;
    private $expectations;
    private $wildcard = MOCK_ANYTHING;
    private $is_strict = true;
    private $call_counts;
    private $expected_counts;
    private $max_counts;
    private $expected_args;
    private $expected_args_at;

    /**
     *    Creates an empty action list and expectation list.
     *    All call counts are set to zero.
     */
    public function __construct()
    {
        $this->actions = new SimpleCallSchedule();
        $this->expectations = new SimpleCallSchedule();
        $this->call_counts = [];
        $this->expected_counts = [];
        $this->max_counts = [];
        $this->expected_args = [];
        $this->expected_args_at = [];
        $this->getCurrentTestCase()->tell($this);
    }

    /**
     *    Disables a name check when setting expectations.
     *    This hack is needed for the partial mocks.
     */
    public function disableExpectationNameChecks()
    {
        $this->is_strict = false;
    }

    /**
     *    Finds currently running test.
     *
     * @return SimpeTestCase current test case
     */
    protected function getCurrentTestCase()
    {
        return SimpleTest::getContext()->getTest();
    }

    /**
     *    Die if bad arguments array is passed.
     *
     * @param mixed $args the arguments value to be checked
     * @param string $task description of task attempt
     *
     * @return bool Valid arguments
     */
    protected function checkArgumentsIsArray($args, $task)
    {
        if (!is_array($args)) {
            trigger_error(
                "Cannot $task as \$args parameter is not an array",
                E_USER_ERROR);
        }
    }

    /**
     *    Triggers a PHP error if the method is not part
     *    of this object.
     *
     * @param string $method name of method
     * @param string $task description of task attempt
     */
    protected function dieOnNoMethod($method, $task)
    {
        if ($this->is_strict && !method_exists($this, $method)) {
            trigger_error(
                "Cannot $task as no {$method}() in class " . get_class($this),
                E_USER_ERROR);
        }
    }

    /**
     *    Replaces wildcard matches with wildcard
     *    expectations in the argument list.
     *
     * @param array $args raw argument list
     *
     * @return array argument list with
     *               expectations
     */
    public function replaceWildcards($args)
    {
        if ($args === false) {
            return false;
        }
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === $this->wildcard) {
                $args[$i] = new AnythingExpectation();
            }
        }
        return $args;
    }

    /**
     *    Adds one to the call count of a method.
     *
     * @param string $method method called
     * @param array $args arguments as an array
     */
    protected function addCall($method, $args)
    {
        if (!isset($this->call_counts[$method])) {
            $this->call_counts[$method] = 0;
        }
        $this->call_counts[$method]++;
    }

    /**
     *    Fetches the call count of a method so far.
     *
     * @param string $method method name called
     *
     * @return int number of calls so far
     */
    public function getCallCount($method)
    {
        $this->dieOnNoMethod($method, 'get call count');
        $method = strtolower($method);
        if (!isset($this->call_counts[$method])) {
            return 0;
        }
        return $this->call_counts[$method];
    }

    /**
     *    Sets a return for a parameter list that will
     *    be passed on by all calls to this method that match.
     *
     * @param string $method method name
     * @param mixed $value result of call by value/handle
     * @param array $args list of parameters to match
     *                    including wildcards
     */
    public function returns($method, $value, $args = false)
    {
        $this->dieOnNoMethod($method, 'set return');
        $this->actions->register($method, $args, new SimpleReturn($value));
    }

    /**
     *    Sets a return for a parameter list that will
     *    be passed only when the required call count
     *    is reached.
     *
     * @param int $timing Number of calls in the future
     *                    to which the result applies. If
     *                    not set then all calls will return
     *                    the value.
     * @param string $method method name
     * @param mixed $value result of call passed
     * @param array $args list of parameters to match
     *                    including wildcards
     */
    public function returnsAt($timing, $method, $value, $args = false)
    {
        $this->dieOnNoMethod($method, 'set return value sequence');
        $this->actions->registerAt($timing, $method, $args, new SimpleReturn($value));
    }

    /**
     *    Sets a return for a parameter list that will
     *    be passed by value for all calls to this method.
     *
     * @param string $method method name
     * @param mixed $value result of call passed by value
     * @param array $args list of parameters to match
     *                    including wildcards
     */
    public function returnsByValue($method, $value, $args = false)
    {
        $this->dieOnNoMethod($method, 'set return value');
        $this->actions->register($method, $args, new SimpleByValue($value));
    }

    /** @deprecated */
    public function setReturnValue($method, $value, $args = false)
    {
        $this->returnsByValue($method, $value, $args);
    }

    /**
     *    Sets a return for a parameter list that will
     *    be passed by value only when the required call count
     *    is reached.
     *
     * @param int $timing Number of calls in the future
     *                    to which the result applies. If
     *                    not set then all calls will return
     *                    the value.
     * @param string $method method name
     * @param mixed $value result of call passed by value
     * @param array $args list of parameters to match
     *                    including wildcards
     */
    public function returnsByValueAt($timing, $method, $value, $args = false)
    {
        $this->dieOnNoMethod($method, 'set return value sequence');
        $this->actions->registerAt($timing, $method, $args, new SimpleByValue($value));
    }

    /** @deprecated */
    public function setReturnValueAt($timing, $method, $value, $args = false)
    {
        $this->returnsByValueAt($timing, $method, $value, $args);
    }

    /**
     *    Sets a return for a parameter list that will
     *    be passed by reference for all calls.
     *
     * @param string $method method name
     * @param mixed $reference result of the call will be this object
     * @param array $args list of parameters to match
     *                    including wildcards
     */
    public function returnsByReference($method, &$reference, $args = false)
    {
        $this->dieOnNoMethod($method, 'set return reference');
        $this->actions->register($method, $args, new SimpleByReference($reference));
    }

    /** @deprecated */
    public function setReturnReference($method, &$reference, $args = false)
    {
        $this->returnsByReference($method, $reference, $args);
    }

    /**
     *    Sets a return for a parameter list that will
     *    be passed by value only when the required call count
     *    is reached.
     *
     * @param int $timing Number of calls in the future
     *                    to which the result applies. If
     *                    not set then all calls will return
     *                    the value.
     * @param string $method method name
     * @param mixed $reference result of the call will be this object
     * @param array $args list of parameters to match
     *                    including wildcards
     */
    public function returnsByReferenceAt($timing, $method, &$reference, $args = false)
    {
        $this->dieOnNoMethod($method, 'set return reference sequence');
        $this->actions->registerAt($timing, $method, $args, new SimpleByReference($reference));
    }

    /** @deprecated */
    public function setReturnReferenceAt($timing, $method, &$reference, $args = false)
    {
        $this->returnsByReferenceAt($timing, $method, $reference, $args);
    }

    /**
     *    Sets up an expected call with a set of
     *    expected parameters in that call. All
     *    calls will be compared to these expectations
     *    regardless of when the call is made.
     *
     * @param string $method method call to test
     * @param array $args expected parameters for the call
     *                    including wildcards
     * @param string $message overridden message
     */
    public function expect($method, $args, $message = '%s')
    {
        $this->dieOnNoMethod($method, 'set expected arguments');
        $this->checkArgumentsIsArray($args, 'set expected arguments');
        $this->expectations->expectArguments($method, $args, $message);
        $args = $this->replaceWildcards($args);
        $message .= Mock::getExpectationLine();
        $this->expected_args[strtolower($method)] =
            new ParametersExpectation($args, $message);
    }

    /**
     *    Sets up an expected call with a set of
     *    expected parameters in that call. The
     *    expected call count will be adjusted if it
     *    is set too low to reach this call.
     *
     * @param int $timing Number of calls in the future at
     *                    which to test. Next call is 0.
     * @param string $method method call to test
     * @param array $args expected parameters for the call
     *                    including wildcards
     * @param string $message overridden message
     */
    public function expectAt($timing, $method, $args, $message = '%s')
    {
        $this->dieOnNoMethod($method, 'set expected arguments at time');
        $this->checkArgumentsIsArray($args, 'set expected arguments at time');
        $args = $this->replaceWildcards($args);
        if (!isset($this->expected_args_at[$timing])) {
            $this->expected_args_at[$timing] = [];
        }
        $method = strtolower($method);
        $message .= Mock::getExpectationLine();
        $this->expected_args_at[$timing][$method] =
            new ParametersExpectation($args, $message);
    }

    /**
     *    Sets an expectation for the number of times
     *    a method will be called. The tally method
     *    is used to check this.
     *
     * @param string $method method call to test
     * @param int $count number of times it should
     *                   have been called at tally
     * @param string $message overridden message
     */
    public function expectCallCount($method, $count, $message = '%s')
    {
        $this->dieOnNoMethod($method, 'set expected call count');
        $message .= Mock::getExpectationLine();
        $this->expected_counts[strtolower($method)] =
            new CallCountExpectation($method, $count, $message);
    }

    /**
     *    Sets the number of times a method may be called
     *    before a test failure is triggered.
     *
     * @param string $method method call to test
     * @param int $count most number of times it should
     *                   have been called
     * @param string $message overridden message
     */
    public function expectMaximumCallCount($method, $count, $message = '%s')
    {
        $this->dieOnNoMethod($method, 'set maximum call count');
        $message .= Mock::getExpectationLine();
        $this->max_counts[strtolower($method)] =
            new MaximumCallCountExpectation($method, $count, $message);
    }

    /**
     *    Sets the number of times to call a method to prevent
     *    a failure on the tally.
     *
     * @param string $method method call to test
     * @param int $count least number of times it should
     *                   have been called
     * @param string $message overridden message
     */
    public function expectMinimumCallCount($method, $count, $message = '%s')
    {
        $this->dieOnNoMethod($method, 'set minimum call count');
        $message .= Mock::getExpectationLine();
        $this->expected_counts[strtolower($method)] =
            new MinimumCallCountExpectation($method, $count, $message);
    }

    /**
     *    Convenience method for barring a method
     *    call.
     *
     * @param string $method method call to ban
     * @param string $message overridden message
     */
    public function expectNever($method, $message = '%s')
    {
        $this->expectMaximumCallCount($method, 0, $message);
    }

    /**
     *    Convenience method for a single method
     *    call.
     *
     * @param string $method method call to track
     * @param array $args expected argument list or
     *                    false for any arguments
     * @param string $message overridden message
     */
    public function expectOnce($method, $args = false, $message = '%s')
    {
        $this->expectCallCount($method, 1, $message);
        if ($args !== false) {
            $this->expect($method, $args, $message);
        }
    }

    /**
     *    Convenience method for requiring a method
     *    call.
     *
     * @param string $method method call to track
     * @param array $args expected argument list or
     *                    false for any arguments
     * @param string $message overridden message
     */
    public function expectAtLeastOnce($method, $args = false, $message = '%s')
    {
        $this->expectMinimumCallCount($method, 1, $message);
        if ($args !== false) {
            $this->expect($method, $args, $message);
        }
    }

    /**
     *    Sets up a trigger to throw an exception upon the
     *    method call.
     *
     * @param string $method method name to throw on
     * @param object $exception Exception object to throw.
     *                          If not given then a simple
     *                          Exception object is thrown.
     * @param array $args Optional argument list filter.
     *                    If given then the exception
     *                    will only be thrown if the
     *                    method call matches the arguments.
     */
    public function throwOn($method, $exception = false, $args = false)
    {
        $this->dieOnNoMethod($method, 'throw on');
        $this->actions->register($method, $args,
            new SimpleThrower($exception ? $exception : new Exception()));
    }

    /**
     *    Sets up a trigger to throw an exception upon the
     *    method call.
     *
     * @param int $timing When to throw the exception. A
     *                    value of 0 throws immediately.
     *                    A value of 1 actually allows one call
     *                    to this method before throwing. 2
     *                    will allow two calls before throwing
     *                    and so on.
     * @param string $method method name to throw on
     * @param object $exception Exception object to throw.
     *                          If not given then a simple
     *                          Exception object is thrown.
     * @param array $args Optional argument list filter.
     *                    If given then the exception
     *                    will only be thrown if the
     *                    method call matches the arguments.
     */
    public function throwAt($timing, $method, $exception = false, $args = false)
    {
        $this->dieOnNoMethod($method, 'throw at');
        $this->actions->registerAt($timing, $method, $args,
            new SimpleThrower($exception ? $exception : new Exception()));
    }

    /**
     *    Sets up a trigger to throw an error upon the
     *    method call.
     *
     * @param string $method method name to throw on
     * @param object $error error message to trigger
     * @param array $args Optional argument list filter.
     *                    If given then the exception
     *                    will only be thrown if the
     *                    method call matches the arguments.
     * @param int $severity The PHP severity level. Defaults
     *                      to E_USER_ERROR.
     */
    public function errorOn($method, $error = 'A mock error', $args = false, $severity = E_USER_ERROR)
    {
        $this->dieOnNoMethod($method, 'error on');
        $this->actions->register($method, $args, new SimpleErrorThrower($error, $severity));
    }

    /**
     *    Sets up a trigger to throw an error upon a specific
     *    method call.
     *
     * @param int $timing When to throw the exception. A
     *                    value of 0 throws immediately.
     *                    A value of 1 actually allows one call
     *                    to this method before throwing. 2
     *                    will allow two calls before throwing
     *                    and so on.
     * @param string $method method name to throw on
     * @param object $error error message to trigger
     * @param array $args Optional argument list filter.
     *                    If given then the exception
     *                    will only be thrown if the
     *                    method call matches the arguments.
     * @param int $severity The PHP severity level. Defaults
     *                      to E_USER_ERROR.
     */
    public function errorAt($timing, $method, $error = 'A mock error', $args = false, $severity = E_USER_ERROR)
    {
        $this->dieOnNoMethod($method, 'error at');
        $this->actions->registerAt($timing, $method, $args, new SimpleErrorThrower($error, $severity));
    }

    /**
     *    Receives event from unit test that the current
     *    test method has finished. Totals up the call
     *    counts and triggers a test assertion if a test
     *    is present for expected call counts.
     *
     * @param string $test_method current method name
     * @param SimpleTestCase $test test to send message to
     */
    public function atTestEnd($test_method, &$test)
    {
        foreach ($this->expected_counts as $method => $expectation) {
            $test->assert($expectation, $this->getCallCount($method));
        }
        foreach ($this->max_counts as $method => $expectation) {
            if ($expectation->test($this->getCallCount($method))) {
                $test->assert($expectation, $this->getCallCount($method));
            }
        }
    }

    /**
     *    Returns the expected value for the method name
     *    and checks expectations. Will generate any
     *    test assertions as a result of expectations
     *    if there is a test present.
     *
     * @param string $method name of method to simulate
     * @param array $args arguments as an array
     *
     * @return mixed stored return
     */
    public function &invoke($method, $args)
    {
        $method = strtolower($method);
        $step = $this->getCallCount($method);
        $this->addCall($method, $args);
        $this->checkExpectations($method, $args, $step);
        $was = $this->disableEStrict();
        try {
            $result = &$this->emulateCall($method, $args, $step);
        } catch (Exception $e) {
            $this->restoreEStrict($was);
            throw $e;
        }
        $this->restoreEStrict($was);
        return $result;
    }

    /**
     *    Finds the return value matching the incoming
     *    arguments. If there is no matching value found
     *    then an error is triggered.
     *
     * @param string $method method name
     * @param array $args calling arguments
     * @param int $step current position in the
     *                  call history
     *
     * @return mixed stored return or other action
     */
    protected function &emulateCall($method, $args, $step)
    {
        return $this->actions->respond($step, $method, $args);
    }

    /**
     *    Tests the arguments against expectations.
     *
     * @param string $method method to check
     * @param array $args argument list to match
     * @param int $timing the position of this call
     *                    in the call history
     */
    protected function checkExpectations($method, $args, $timing)
    {
        $test = $this->getCurrentTestCase();
        if (isset($this->max_counts[$method])) {
            if (!$this->max_counts[$method]->test($timing + 1)) {
                $test->assert($this->max_counts[$method], $timing + 1);
            }
        }
        if (isset($this->expected_args_at[$timing][$method])) {
            $test->assert(
                $this->expected_args_at[$timing][$method],
                $args,
                "Mock method [$method] at [$timing] -> %s");
        } elseif (isset($this->expected_args[$method])) {
            $test->assert(
                $this->expected_args[$method],
                $args,
                "Mock method [$method] -> %s");
        }
    }

    /**
     *   Our mock has to be able to return anything, including
     *   variable references. To allow for these mixed returns
     *   we have to disable the E_STRICT warnings while the
     *   method calls are emulated.
     */
    private function disableEStrict()
    {
        $was = error_reporting();
        error_reporting($was & ~E_STRICT);
        return $was;
    }

    /**
     *  Restores the E_STRICT level if it was previously set.
     *
     * @param int $was previous error reporting level
     */
    private function restoreEStrict($was)
    {
        error_reporting($was);
    }
}

/**
 *    Static methods only service class for code generation of
 *    mock objects.
 */
class Mock
{
    /**
     *    Factory for mock object classes.
     */
    public function __construct()
    {
        trigger_error('Mock factory methods are static.');
    }

    /**
     *    Clones a class' interface and creates a mock version
     *    that can have return values and expectations set.
     *
     * @param string $class class to clone
     * @param string $mock_class New class name. Default is
     *                           the old name with "Mock"
     *                           prepended.
     * @param array $methods Additional methods to add beyond
     *                       those in the cloned class. Use this
     *                       to emulate the dynamic addition of
     *                       methods in the cloned class or when
     *                       the class hasn't been written yet.sta
     */
    public static function generate($class, $mock_class = false, $methods = false)
    {
        $generator = new MockGenerator($class, $mock_class);
        return @$generator->generateSubclass($methods);
    }

    /**
     *    Generates a version of a class with selected
     *    methods mocked only. Inherits the old class
     *    and chains the mock methods of an aggregated
     *    mock object.
     *
     * @param string $class class to clone
     * @param string $mock_class new class name
     * @param array $methods methods to be overridden
     *                       with mock versions
     */
    public static function generatePartial($class, $mock_class, $methods)
    {
        $generator = new MockGenerator($class, $mock_class);
        return @$generator->generatePartial($methods);
    }

    /**
     *    Uses a stack trace to find the line of an assertion.
     */
    public static function getExpectationLine()
    {
        $trace = new SimpleStackTrace(['expect']);
        return $trace->traceMethod();
    }
}

/**
 *    Service class for code generation of mock objects.
 */
class MockGenerator
{
    private $class;
    private $mock_class;
    private $mock_base;
    private $reflection;

    /**
     *    Builds initial reflection object.
     *
     * @param string $class class to be mocked
     * @param string $mock_class new class with identical interface,
     *                           but no behaviour
     */
    public function __construct($class, $mock_class)
    {
        $this->class = $class;
        $this->mock_class = $mock_class;
        if (!$this->mock_class) {
            $this->mock_class = 'Mock' . $this->class;
        }
        $this->mock_base = SimpleTest::getMockBaseClass();
        $this->reflection = new SimpleReflection($this->class);
    }

    /**
     *    Clones a class' interface and creates a mock version
     *    that can have return values and expectations set.
     *
     * @param array $methods Additional methods to add beyond
     *                       those in th cloned class. Use this
     *                       to emulate the dynamic addition of
     *                       methods in the cloned class or when
     *                       the class hasn't been written yet.
     */
    public function generate($methods)
    {
        if (!$this->reflection->classOrInterfaceExists()) {
            return false;
        }
        $mock_reflection = new SimpleReflection($this->mock_class);
        if ($mock_reflection->classExistsSansAutoload()) {
            return false;
        }
        $code = $this->createClassCode($methods ? $methods : []);
        return eval("$code return \$code;");
    }

    /**
     *    Subclasses a class and overrides every method with a mock one
     *    that can have return values and expectations set. Chains
     *    to an aggregated SimpleMock.
     *
     * @param array $methods Additional methods to add beyond
     *                       those in the cloned class. Use this
     *                       to emulate the dynamic addition of
     *                       methods in the cloned class or when
     *                       the class hasn't been written yet.
     */
    public function generateSubclass($methods)
    {
        if (!$this->reflection->classOrInterfaceExists()) {
            return false;
        }
        $mock_reflection = new SimpleReflection($this->mock_class);
        if ($mock_reflection->classExistsSansAutoload()) {
            return false;
        }
        if ($this->reflection->isInterface() || $this->reflection->hasFinal()) {
            $code = $this->createClassCode($methods ? $methods : []);
            return eval("$code return \$code;");
        } else {
            $code = $this->createSubclassCode($methods ? $methods : []);
            return eval("$code return \$code;");
        }
    }

    /**
     *    Generates a version of a class with selected
     *    methods mocked only. Inherits the old class
     *    and chains the mock methods of an aggregated
     *    mock object.
     *
     * @param array $methods methods to be overridden
     *                       with mock versions
     */
    public function generatePartial($methods)
    {
        if (!$this->reflection->classExists($this->class)) {
            return false;
        }
        $mock_reflection = new SimpleReflection($this->mock_class);
        if ($mock_reflection->classExistsSansAutoload()) {
            trigger_error('Partial mock class [' . $this->mock_class . '] already exists');
            return false;
        }
        $code = $this->extendClassCode($methods);
        return eval("$code return \$code;");
    }

    /**
     *    The new mock class code as a string.
     *
     * @param array $methods additional methods
     *
     * @return string code for new mock class
     */
    protected function createClassCode($methods)
    {
        $implements = '';
        $interfaces = $this->reflection->getInterfaces();
        $interfaces = array_diff($interfaces, ['Traversable']);
        if (count($interfaces) > 0) {
            $implements = 'implements ' . implode(', ', $interfaces);
        }
        $code = 'class ' . $this->mock_class . ' extends ' . $this->mock_base . " $implements {\n";
        $code .= '    function ' . $this->mock_class . "() {\n";
        $code .= '        $this->' . $this->mock_base . "();\n";
        $code .= "    }\n";
        if (in_array('__construct', $this->reflection->getMethods())) {
            $code .= "    function __construct() {\n";
            $code .= '        $this->' . $this->mock_base . "();\n";
            $code .= "    }\n";
        }
        $code .= $this->createHandlerCode($methods);
        $code .= "}\n";
        return $code;
    }

    /**
     *    The new mock class code as a string. The mock will
     *    be a subclass of the original mocked class.
     *
     * @param array $methods additional methods
     *
     * @return string code for new mock class
     */
    protected function createSubclassCode($methods)
    {
        $code = 'class ' . $this->mock_class . ' extends ' . $this->class . " {\n";
        $code .= "    public \$mock;\n";
        $code .= $this->addMethodList(array_merge($methods, $this->reflection->getMethods()));
        $code .= "\n";
        $code .= '    function ' . $this->mock_class . "() {\n";
        $code .= '        $this->mock = new ' . $this->mock_base . "();\n";
        $code .= "        \$this->mock->disableExpectationNameChecks();\n";
        $code .= "    }\n";
        $code .= $this->chainMockReturns();
        $code .= $this->chainMockExpectations();
        $code .= $this->chainThrowMethods();
        $code .= $this->overrideMethods($this->reflection->getMethods());
        $code .= $this->createNewMethodCode($methods);
        $code .= "}\n";
        return $code;
    }

    /**
     *    The extension class code as a string. The class
     *    composites a mock object and chains mocked methods
     *    to it.
     *
     * @param array $methods mocked methods
     *
     * @return string code for a new class
     */
    protected function extendClassCode($methods)
    {
        $code = 'class ' . $this->mock_class . ' extends ' . $this->class . " {\n";
        $code .= "    protected \$mock;\n";
        $code .= $this->addMethodList($methods);
        $code .= "\n";
        $code .= '    function ' . $this->mock_class . "() {\n";
        $code .= '        $this->mock = new ' . $this->mock_base . "();\n";
        $code .= "        \$this->mock->disableExpectationNameChecks();\n";
        $code .= "    }\n";
        $code .= $this->chainMockReturns();
        $code .= $this->chainMockExpectations();
        $code .= $this->chainThrowMethods();
        $code .= $this->overrideMethods($methods);
        $code .= "}\n";
        return $code;
    }

    /**
     *    Creates code within a class to generate replaced
     *    methods. All methods call the invoke() handler
     *    with the method name and the arguments in an
     *    array.
     *
     * @param array $methods additional methods
     */
    protected function createHandlerCode($methods)
    {
        $code = '';
        $methods = array_merge($methods, $this->reflection->getMethods());
        foreach ($methods as $method) {
            if ($this->isConstructor($method)) {
                continue;
            }
            $mock_reflection = new SimpleReflection($this->mock_base);
            if (in_array($method, $mock_reflection->getMethods())) {
                continue;
            }
            $code .= '    ' . $this->reflection->getSignature($method) . " {\n";
            $code .= "        \$args = func_get_args();\n";
            $code .= "        \$result = &\$this->invoke(\"$method\", \$args);\n";
            $code .= "        return \$result;\n";
            $code .= "    }\n";
        }
        return $code;
    }

    /**
     *    Creates code within a class to generate a new
     *    methods. All methods call the invoke() handler
     *    on the internal mock with the method name and
     *    the arguments in an array.
     *
     * @param array $methods additional methods
     */
    protected function createNewMethodCode($methods)
    {
        $code = '';
        foreach ($methods as $method) {
            if ($this->isConstructor($method)) {
                continue;
            }
            $mock_reflection = new SimpleReflection($this->mock_base);
            if (in_array($method, $mock_reflection->getMethods())) {
                continue;
            }
            $code .= '    ' . $this->reflection->getSignature($method) . " {\n";
            $code .= "        \$args = func_get_args();\n";
            $code .= "        \$result = &\$this->mock->invoke(\"$method\", \$args);\n";
            $code .= "        return \$result;\n";
            $code .= "    }\n";
        }
        return $code;
    }

    /**
     *    Tests to see if a special PHP method is about to
     *    be stubbed by mistake.
     *
     * @param string $method method name
     *
     * @return bool true if special
     */
    protected function isConstructor($method)
    {
        return in_array(
            strtolower($method),
            ['__construct', '__destruct']);
    }

    /**
     *    Creates a list of mocked methods for error checking.
     *
     * @param array $methods mocked methods
     *
     * @return string code for a method list
     */
    protected function addMethodList($methods)
    {
        return "    protected \$mocked_methods = array('" .
        implode("', '", array_map('strtolower', $methods)) .
        "');\n";
    }

    /**
     *    Creates code to abandon the expectation if not mocked.
     *
     * @param string $alias parameter name of method name
     *
     * @return string code for bail out
     */
    protected function bailOutIfNotMocked($alias)
    {
        $code = "        if (! in_array(strtolower($alias), \$this->mocked_methods)) {\n";
        $code .= "            trigger_error(\"Method [$alias] is not mocked\");\n";
        $code .= "            \$null = null;\n";
        $code .= "            return \$null;\n";
        $code .= "        }\n";
        return $code;
    }

    /**
     *    Creates source code for chaining to the composited
     *    mock object.
     *
     * @return string code for mock set up
     */
    protected function chainMockReturns()
    {
        $code = "    function returns(\$method, \$value, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->returns(\$method, \$value, \$args);\n";
        $code .= "    }\n";
        $code .= "    function returnsAt(\$timing, \$method, \$value, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->returnsAt(\$timing, \$method, \$value, \$args);\n";
        $code .= "    }\n";
        $code .= "    function returnsByValue(\$method, \$value, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->setReturnValue(\$method, \$value, \$args);\n";
        $code .= "    }\n";
        $code .= "    function returnsByValueAt(\$timing, \$method, \$value, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->setReturnValueAt(\$timing, \$method, \$value, \$args);\n";
        $code .= "    }\n";
        $code .= "    function returnsByReference(\$method, &\$ref, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->setReturnReference(\$method, \$ref, \$args);\n";
        $code .= "    }\n";
        $code .= "    function returnsByReferenceAt(\$timing, \$method, &\$ref, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->setReturnReferenceAt(\$timing, \$method, \$ref, \$args);\n";
        $code .= "    }\n";
        $code .= "    function setReturnValue(\$method, \$value, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->setReturnValue(\$method, \$value, \$args);\n";
        $code .= "    }\n";
        $code .= "    function setReturnValueAt(\$timing, \$method, \$value, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->setReturnValueAt(\$timing, \$method, \$value, \$args);\n";
        $code .= "    }\n";
        $code .= "    function setReturnReference(\$method, &\$ref, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->setReturnReference(\$method, \$ref, \$args);\n";
        $code .= "    }\n";
        $code .= "    function setReturnReferenceAt(\$timing, \$method, &\$ref, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->setReturnReferenceAt(\$timing, \$method, \$ref, \$args);\n";
        $code .= "    }\n";
        return $code;
    }

    /**
     *    Creates source code for chaining to an aggregated
     *    mock object.
     *
     * @return string code for expectations
     */
    protected function chainMockExpectations()
    {
        $code = "    function expect(\$method, \$args = false, \$msg = '%s') {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->expect(\$method, \$args, \$msg);\n";
        $code .= "    }\n";
        $code .= "    function expectAt(\$timing, \$method, \$args = false, \$msg = '%s') {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->expectAt(\$timing, \$method, \$args, \$msg);\n";
        $code .= "    }\n";
        $code .= "    function expectCallCount(\$method, \$count) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->expectCallCount(\$method, \$count, \$msg = '%s');\n";
        $code .= "    }\n";
        $code .= "    function expectMaximumCallCount(\$method, \$count, \$msg = '%s') {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->expectMaximumCallCount(\$method, \$count, \$msg = '%s');\n";
        $code .= "    }\n";
        $code .= "    function expectMinimumCallCount(\$method, \$count, \$msg = '%s') {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->expectMinimumCallCount(\$method, \$count, \$msg = '%s');\n";
        $code .= "    }\n";
        $code .= "    function expectNever(\$method) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->expectNever(\$method);\n";
        $code .= "    }\n";
        $code .= "    function expectOnce(\$method, \$args = false, \$msg = '%s') {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->expectOnce(\$method, \$args, \$msg);\n";
        $code .= "    }\n";
        $code .= "    function expectAtLeastOnce(\$method, \$args = false, \$msg = '%s') {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->expectAtLeastOnce(\$method, \$args, \$msg);\n";
        $code .= "    }\n";
        return $code;
    }

    /**
     *    Adds code for chaining the throw methods.
     *
     * @return string code for chains
     */
    protected function chainThrowMethods()
    {
        $code = "    function throwOn(\$method, \$exception = false, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->throwOn(\$method, \$exception, \$args);\n";
        $code .= "    }\n";
        $code .= "    function throwAt(\$timing, \$method, \$exception = false, \$args = false) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->throwAt(\$timing, \$method, \$exception, \$args);\n";
        $code .= "    }\n";
        $code .= "    function errorOn(\$method, \$error = 'A mock error', \$args = false, \$severity = E_USER_ERROR) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->errorOn(\$method, \$error, \$args, \$severity);\n";
        $code .= "    }\n";
        $code .= "    function errorAt(\$timing, \$method, \$error = 'A mock error', \$args = false, \$severity = E_USER_ERROR) {\n";
        $code .= $this->bailOutIfNotMocked('$method');
        $code .= "        \$this->mock->errorAt(\$timing, \$method, \$error, \$args, \$severity);\n";
        $code .= "    }\n";
        return $code;
    }

    /**
     *    Creates source code to override a list of methods
     *    with mock versions.
     *
     * @param array $methods methods to be overridden
     *                       with mock versions
     *
     * @return string code for overridden chains
     */
    protected function overrideMethods($methods)
    {
        $code = '';
        foreach ($methods as $method) {
            if ($this->isConstructor($method)) {
                continue;
            }
            $code .= '    ' . $this->reflection->getSignature($method) . " {\n";
            $code .= "        \$args = func_get_args();\n";
            $code .= "        \$result = &\$this->mock->invoke(\"$method\", \$args);\n";
            $code .= "        return \$result;\n";
            $code .= "    }\n";
        }
        return $code;
    }
}
