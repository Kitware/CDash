<?php

/**
 *  base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/test_case.php';
/**#@-*/

/**
 *    Wrapper for exec() functionality.
 */
class SimpleShell
{
    private $output;

    /**
     *    Executes the shell comand and stashes the output.
     */
    public function __construct()
    {
        $this->output = false;
    }

    /**
     *    Actually runs the command. Does not trap the
     *    error stream output as this need PHP 4.3+.
     *
     * @param string $command the actual command line
     *                        to run
     *
     * @return int exit code
     */
    public function execute($command)
    {
        $this->output = false;
        exec($command, $this->output, $ret);
        return $ret;
    }

    /**
     *    Accessor for the last output.
     *
     * @return string output as text
     */
    public function getOutput()
    {
        return implode("\n", $this->output);
    }

    /**
     *    Accessor for the last output.
     *
     * @return array output as array of lines
     */
    public function getOutputAsList()
    {
        return $this->output;
    }
}

/**
 *    Test case for testing of command line scripts and
 *    utilities. Usually scripts that are external to the
 *    PHP code, but support it in some way.
 */
class ShellTestCase extends SimpleTestCase
{
    private $current_shell;
    private $last_status;
    private $last_command;

    /**
     *    Creates an empty test case. Should be subclassed
     *    with test methods for a functional test case.
     *
     * @param string $label Name of test case. Will use
     *                      the class name if none specified.
     */
    public function __construct($label = false)
    {
        parent::__construct($label);
        $this->current_shell = $this->createShell();
        $this->last_status = false;
        $this->last_command = '';
    }

    /**
     *    Executes a command and buffers the results.
     *
     * @param string $command command to run
     *
     * @return bool true if zero exit code
     */
    public function execute($command)
    {
        $shell = $this->getShell();
        $this->last_status = $shell->execute($command);
        $this->last_command = $command;
        return $this->last_status === 0;
    }

    /**
     *    Dumps the output of the last command.
     */
    public function dumpOutput()
    {
        $this->dump($this->getOutput());
    }

    /**
     *    Accessor for the last output.
     *
     * @return string output as text
     */
    public function getOutput()
    {
        $shell = $this->getShell();
        return $shell->getOutput();
    }

    /**
     *    Accessor for the last output.
     *
     * @return array output as array of lines
     */
    public function getOutputAsList()
    {
        $shell = $this->getShell();
        return $shell->getOutputAsList();
    }

    /**
     *    Called from within the test methods to register
     *    passes and failures.
     *
     * @param bool $result pass on true
     * @param string $message message to display describing
     *                        the test state
     *
     * @return bool True on pass
     */
    public function assertTrue($result, $message = false)
    {
        return $this->assert(new TrueExpectation(), $result, $message);
    }

    /**
     *    Will be true on false and vice versa. False
     *    is the PHP definition of false, so that null,
     *    empty strings, zero and an empty array all count
     *    as false.
     *
     * @param bool $result pass on false
     * @param string $message message to display
     *
     * @return bool True on pass
     */
    public function assertFalse($result, $message = '%s')
    {
        return $this->assert(new FalseExpectation(), $result, $message);
    }

    /**
     *    Will trigger a pass if the two parameters have
     *    the same value only. Otherwise a fail. This
     *    is for testing hand extracted text, etc.
     *
     * @param mixed $first value to compare
     * @param mixed $second value to compare
     * @param string $message message to display
     *
     * @return bool True on pass
     */
    public function assertEqual($first, $second, $message = '%s')
    {
        return $this->assert(
            new EqualExpectation($first),
            $second,
            $message);
    }

    /**
     *    Will trigger a pass if the two parameters have
     *    a different value. Otherwise a fail. This
     *    is for testing hand extracted text, etc.
     *
     * @param mixed $first value to compare
     * @param mixed $second value to compare
     * @param string $message message to display
     *
     * @return bool True on pass
     */
    public function assertNotEqual($first, $second, $message = '%s')
    {
        return $this->assert(
            new NotEqualExpectation($first),
            $second,
            $message);
    }

    /**
     *    Tests the last status code from the shell.
     *
     * @param int $status expected status of last
     *                    command
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertExitCode($status, $message = '%s')
    {
        $message = sprintf($message, "Expected status code of [$status] from [" .
            $this->last_command . '], but got [' .
            $this->last_status . ']');
        return $this->assertTrue($status === $this->last_status, $message);
    }

    /**
     *    Attempt to exactly match the combined STDERR and
     *    STDOUT output.
     *
     * @param string $expected expected output
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertOutput($expected, $message = '%s')
    {
        $shell = $this->getShell();
        return $this->assert(
            new EqualExpectation($expected),
            $shell->getOutput(),
            $message);
    }

    /**
     *    Scans the output for a Perl regex. If found
     *    anywhere it passes, else it fails.
     *
     * @param string $pattern regex to search for
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertOutputPattern($pattern, $message = '%s')
    {
        $shell = $this->getShell();
        return $this->assert(
            new PatternExpectation($pattern),
            $shell->getOutput(),
            $message);
    }

    /**
     *    If a Perl regex is found anywhere in the current
     *    output then a failure is generated, else a pass.
     *
     * @param string $pattern regex to search for
     * @param $message Message to display
     *
     * @return bool true if pass
     */
    public function assertNoOutputPattern($pattern, $message = '%s')
    {
        $shell = $this->getShell();
        return $this->assert(
            new NoPatternExpectation($pattern),
            $shell->getOutput(),
            $message);
    }

    /**
     *    File existence check.
     *
     * @param string $path full filename and path
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertFileExists($path, $message = '%s')
    {
        $message = sprintf($message, "File [$path] should exist");
        return $this->assertFileExists($path, $message);
    }

    /**
     *    File non-existence check.
     *
     * @param string $path full filename and path
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertFileNotExists($path, $message = '%s')
    {
        $message = sprintf($message, "File [$path] should not exist");
        return $this->assertFileDoesNotExist($path, $message);
    }

    /**
     *    Scans a file for a Perl regex. If found
     *    anywhere it passes, else it fails.
     *
     * @param string $pattern regex to search for
     * @param string $path full filename and path
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertFilePattern($pattern, $path, $message = '%s')
    {
        return $this->assert(
            new PatternExpectation($pattern),
            implode('', file($path)),
            $message);
    }

    /**
     *    If a Perl regex is found anywhere in the named
     *    file then a failure is generated, else a pass.
     *
     * @param string $pattern regex to search for
     * @param string $path full filename and path
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertNoFilePattern($pattern, $path, $message = '%s')
    {
        return $this->assert(
            new NoPatternExpectation($pattern),
            implode('', file($path)),
            $message);
    }

    /**
     *    Accessor for current shell. Used for testing the
     *    the tester itself.
     *
     * @return Shell current shell
     */
    protected function getShell()
    {
        return $this->current_shell;
    }

    /**
     *    Factory for the shell to run the command on.
     *
     * @return Shell new shell object
     */
    protected function createShell()
    {
        return new SimpleShell();
    }
}
