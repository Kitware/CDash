<?php

$root = dirname(dirname(__FILE__));
chdir($root);

require_once "xml_handlers/build_handler.php";
require_once "include/messaging/Message.php";


/**
 * MessageTest
 */
class MessageTest extends \PHPUnit_Framework_TestCase
{
    private $buildHandler;
    private $projectId = 5;
    private $scheduleId = 2;

    public function setUp()
    {
        parent::setUp();

        $this->buildHandler = $this->getMock(
            'BuildHandler',
            ['getActionableBuilds'],
            [$this->projectId, $this->scheduleId]
        );
    }

    public function testMessageConstruct()
    {
        $message = new Message($this->buildHandler);

        $this->assertInstanceOf('Message', $message);
    }
}
