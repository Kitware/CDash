<?php

/**
 *  base include file for eclipse plugin
 *
 * @version    $Id$
 */
/**#@+
 * simpletest include files
 */
include_once 'unit_tester.php';
include_once 'test_case.php';
include_once 'invoker.php';
include_once 'socket.php';
include_once 'mock_objects.php';
/**#@-*/

/**
 *  base reported class for eclipse plugin
 */
class EclipseReporter extends SimpleScorer
{
    /**
     *    Reporter to be run inside of Eclipse interface.
     *
     * @param object $listener eclipse listener (?)
     * @param bool $cc whether to include test coverage
     */
    public function __construct(&$listener, $cc = false)
    {
        $this->listener = &$listener;
        $this->SimpleScorer();
        $this->case = '';
        $this->group = '';
        $this->method = '';
        $this->cc = $cc;
        $this->error = false;
        $this->fail = false;
    }

    /**
     *    Means to display human readable object comparisons.
     *
     * @return SimpleDumper visual comparer
     */
    public function getDumper()
    {
        return new SimpleDumper();
    }

    /**
     *    Localhost connection from Eclipse.
     *
     * @param int $port port to connect to Eclipse
     * @param string $host normally localhost
     *
     * @return SimpleSocket connection to Eclipse
     */
    public function &createListener($port, $host = '127.0.0.1')
    {
        $tmplistener = new SimpleSocket($host, $port, 5);
        return $tmplistener;
    }

    /**
     *    Wraps the test in an output buffer.
     *
     * @param SimpleInvoker $invoker current test runner
     *
     * @return EclipseInvoker decorator with output buffering
     */
    public function &createInvoker(&$invoker)
    {
        $eclinvoker = new EclipseInvoker($invoker, $this->listener);
        return $eclinvoker;
    }

    /**
     *    C style escaping.
     *
     * @param string $raw string with backslashes, quotes and whitespace
     *
     * @return string replaced with C backslashed tokens
     */
    public function escapeVal($raw)
    {
        $needle = ['\\', '"', '/', "\b", "\f", "\n", "\r", "\t"];
        $replace = ['\\\\', '\"', '\/', '\b', '\f', '\n', '\r', '\t'];
        return str_replace($needle, $replace, $raw);
    }

    /**
     *    Stash the first passing item. Clicking the test
     *    item goes to first pass.
     *
     * @param string $message test message, but we only wnat the first
     */
    public function paintPass($message)
    {
        if (!$this->pass) {
            $this->message = $this->escapeVal($message);
        }
        $this->pass = true;
    }

    /**
     *    Stash the first failing item. Clicking the test
     *    item goes to first fail.
     *
     * @param string $message test message, but we only wnat the first
     */
    public function paintFail($message)
    {
        // only get the first failure or error
        if (!$this->fail && !$this->error) {
            $this->fail = true;
            $this->message = $this->escapeVal($message);
            $this->listener->write('{status:"fail",message:"' . $this->message . '",group:"' . $this->group . '",case:"' . $this->case . '",method:"' . $this->method . '"}');
        }
    }

    /**
     *    Stash the first error. Clicking the test
     *    item goes to first error.
     *
     * @param string $message test message, but we only wnat the first
     */
    public function paintError($message)
    {
        if (!$this->fail && !$this->error) {
            $this->error = true;
            $this->message = $this->escapeVal($message);
            $this->listener->write('{status:"error",message:"' . $this->message . '",group:"' . $this->group . '",case:"' . $this->case . '",method:"' . $this->method . '"}');
        }
    }

    /**
     *    Stash the first exception. Clicking the test
     *    item goes to first message.
     */
    public function paintException($exception)
    {
        if (!$this->fail && !$this->error) {
            $this->error = true;
            $message = 'Unexpected exception of type[' . get_class($exception) .
                '] with message [' . $exception->getMessage() . '] in [' .
                $exception->getFile() . ' line ' . $exception->getLine() . ']';
            $this->message = $this->escapeVal($message);
            $this->listener->write(
                '{status:"error",message:"' . $this->message . '",group:"' .
                $this->group . '",case:"' . $this->case . '",method:"' . $this->method
                . '"}');
        }
    }

    /**
     *    We don't display any special header.
     *
     * @param string $test_name first test top level
     *                          to start
     */
    public function paintHeader($test_name)
    {
    }

    /**
     *    We don't display any special footer.
     *
     * @param string $test_name the top level test
     */
    public function paintFooter($test_name)
    {
    }

    /**
     *    Paints nothing at the start of a test method, but stash
     *    the method name for later.
     */
    public function paintMethodStart($method)
    {
        $this->pass = false;
        $this->fail = false;
        $this->error = false;
        $this->method = $this->escapeVal($method);
    }

    /**
     *    Only send one message if the test passes, after that
     *    suppress the message.
     */
    public function paintMethodEnd($method)
    {
        if ($this->fail || $this->error || !$this->pass) {
        } else {
            $this->listener->write(
                '{status:"pass",message:"' . $this->message . '",group:"' .
                $this->group . '",case:"' . $this->case . '",method:"' .
                $this->method . '"}');
        }
    }

    /**
     *    Stashes the test case name for the later failure message.
     */
    public function paintCaseStart($case)
    {
        $this->case = $this->escapeVal($case);
    }

    /**
     *    Drops the name.
     */
    public function paintCaseEnd($case)
    {
        $this->case = '';
    }

    /**
     *    Stashes the name of the test suite. Starts test coverage
     *    if enabled.
     *
     * @param string $group name of test or other label
     * @param int $size number of test cases starting
     */
    public function paintGroupStart($group, $size)
    {
        $this->group = $this->escapeVal($group);
        if ($this->cc) {
            if (extension_loaded('xdebug')) {
                xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
            }
        }
    }

    /**
     *    Paints coverage report if enabled.
     *
     * @param string $group name of test or other label
     */
    public function paintGroupEnd($group)
    {
        $this->group = '';
        $cc = '';
        if ($this->cc) {
            if (extension_loaded('xdebug')) {
                $arrfiles = xdebug_get_code_coverage();
                xdebug_stop_code_coverage();
                $thisdir = dirname(__FILE__);
                $thisdirlen = strlen($thisdir);
                foreach ($arrfiles as $index => $file) {
                    if (substr($index, 0, $thisdirlen) === $thisdir) {
                        continue;
                    }
                    $lcnt = 0;
                    $ccnt = 0;
                    foreach ($file as $line) {
                        if ($line == -2) {
                            continue;
                        }
                        $lcnt++;
                        if ($line == 1) {
                            $ccnt++;
                        }
                    }
                    if ($lcnt > 0) {
                        $cc .= round(($ccnt / $lcnt) * 100, 2) . '%';
                    } else {
                        $cc .= '0.00%';
                    }
                    $cc .= "\t" . $index . "\n";
                }
            }
        }
        $this->listener->write('{status:"coverage",message:"' .
            EclipseReporter::escapeVal($cc) . '"}');
    }
}

/**
 *  Invoker decorator for Eclipse. Captures output until
 *  the end of the test.
 */
class EclipseInvoker extends SimpleInvokerDecorator
{
    public function __construct(&$invoker, &$listener)
    {
        $this->listener = &$listener;
        $this->SimpleInvokerDecorator($invoker);
    }

    /**
     *    Starts output buffering.
     *
     * @param string $method test method to call
     */
    public function before($method)
    {
        ob_start();
        $this->invoker->before($method);
    }

    /**
     *    Stops output buffering and send the captured output
     *    to the listener.
     *
     * @param string $method test method to call
     */
    public function after($method)
    {
        $this->invoker->after($method);
        $output = ob_get_contents();
        ob_end_clean();
        if ($output !== '') {
            $result = $this->listener->write('{status:"info",message:"' .
                EclipseReporter::escapeVal($output) . '"}');
        }
    }
}
