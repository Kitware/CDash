<?php

/**
 *  base include file for SimpleTest
 * @author Rene vd O (original code)
 * @author Perrick Penet
 * @author Marcus Baker
 * @version  $Id$
 */

/**
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/scorer.php';

/**
 *  A single test result.
 */
abstract class SimpleResult
{
    public $time;
    public $breadcrumb;
    public $message;

    /**
     * Records the test result as public members.
     * @param array $breadcrumb Test stack at the time of the event.
     * @param string $message The messsage to the human.
     */
    public function __construct($breadcrumb, $message)
    {
        [$this->time, $this->breadcrumb, $this->message] =
            [time(), $breadcrumb, $message];
    }
}

/**
 *    A single pass captured for later.
 */
class SimpleResultOfPass extends SimpleResult
{
}

/**
 *    A single failure captured for later.
 */
class SimpleResultOfFail extends SimpleResult
{
}

/**
 *    A single exception captured for later.
 */
class SimpleResultOfException extends SimpleResult
{
}

/**
 *    Array-based test recorder. Returns an array
 *    with timestamp, status, test name and message for each pass and failure.
 */
class Recorder extends SimpleReporterDecorator
{
    public $results = [];

    /**
     *    Stashes the pass as a SimpleResultOfPass
     *    for later retrieval.
     * @param string $message Pass message to be displayed
     *                              eventually.
     */
    public function paintPass($message)
    {
        parent::paintPass($message);
        $this->results[] = new SimpleResultOfPass(parent::getTestList(), $message);
    }

    /**
     *    Stashes the fail as a SimpleResultOfFail
     *    for later retrieval.
     * @param string $message Failure message to be displayed
     *                              eventually.
     */
    public function paintFail($message)
    {
        parent::paintFail($message);
        $this->results[] = new SimpleResultOfFail(parent::getTestList(), $message);
    }

    /**
     *    Stashes the exception as a SimpleResultOfException
     *    for later retrieval.
     * @param string $message Exception message to be displayed
     *                              eventually.
     */
    public function paintException($message)
    {
        parent::paintException($message);
        $this->results[] = new SimpleResultOfException(parent::getTestList(), $message);
    }
}
