<?php
require_once 'include/pdo.php';
require_once 'include/do_submit.php';

use CDash\Config;
use CDash\Messaging\Message;
use CDash\Messaging\MessageBuilderFactory;

/**
 * class MeassagingTest
 */
class MessagingTest extends PHPUnit_Framework_TestCase
{
    public function testMessaging()
    {
        global $CDASH_DB_HOST, $CDASH_DB_LOGIN, $CDASH_DB_PASS, $CDASH_DB_NAME;
        include 'config/config.local.php';

        global $cdash_database_connection;
        $cdash_database_connection = null;
        get_link_identifier();

        $_SERVER['REMOTE_ADDR'] = '::1';
        $config = Config::getInstance();
        $app_path = $config->get('CDASH_ROOT_DIR') . '/tests/data/MultipleSubProjects';
        $filename = 'Build.xml';
        $projectId = '6';
        $fh = fopen("{$app_path}/{$filename}", 'r');

        ob_start();
        $handler = ctest_parse($fh, $projectId);
        ob_end_clean();

        sendemail($handler, $projectId);

        /*
        $factory = new MessageFactory();
        $message = $factory->createMessage($handler, Message::TYPE_EMAIL);
        $message->send();
        */
    }
}
