<?php

/**
 *  base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/xml.php';
require_once dirname(__FILE__) . '/shell_tester.php';
/**#@-*/

/**
 *    Runs an XML formated test in a separate process.
 */
class DetachedTestCase
{
    private $command;
    private $dry_command;
    private $size;

    /**
     *    Sets the location of the remote test.
     *
     * @param string $command test script
     * @param string $dry_command script for dry run
     */
    public function __construct($command, $dry_command = false)
    {
        $this->command = $command;
        $this->dry_command = $dry_command ? $dry_command : $command;
        $this->size = false;
    }

    /**
     *    Accessor for the test name for subclasses.
     *
     * @return string name of the test
     */
    public function getLabel()
    {
        return $this->command;
    }

    /**
     *    Runs the top level test for this class. Currently
     *    reads the data as a single chunk. I'll fix this
     *    once I have added iteration to the browser.
     *
     * @param SimpleReporter $reporter target of test results
     *
     * @returns boolean                   True if no failures.
     */
    public function run(&$reporter)
    {
        $shell = new SimpleShell();
        $shell->execute($this->command);
        $parser = &$this->createParser($reporter);
        if (!$parser->parse($shell->getOutput())) {
            trigger_error('Cannot parse incoming XML from [' . $this->command . ']');
            return false;
        }
        return true;
    }

    /**
     *    Accessor for the number of subtests.
     *
     * @return int number of test cases
     */
    public function getSize()
    {
        if ($this->size === false) {
            $shell = new SimpleShell();
            $shell->execute($this->dry_command);
            $reporter = new SimpleReporter();
            $parser = &$this->createParser($reporter);
            if (!$parser->parse($shell->getOutput())) {
                trigger_error('Cannot parse incoming XML from [' . $this->dry_command . ']');
                return false;
            }
            $this->size = $reporter->getTestCaseCount();
        }
        return $this->size;
    }

    /**
     *    Creates the XML parser.
     *
     * @param SimpleReporter $reporter target of test results
     *
     * @return SimpleTestXmlListener XML reader
     */
    protected function &createParser(&$reporter)
    {
        return new SimpleTestXmlParser($reporter);
    }
}
