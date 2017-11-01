<?php
namespace CDash;

require_once 'include/log.php';

class Log extends Singleton
{
    protected function write(\Exception $e, $level)
    {
        $message = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
        $trace = $e->getTrace();
        $function = $trace[0]['function'];
        add_log($message, $function, $level);
    }

    public function info(\Exception $e)
    {
        $this->write($e, LOG_INFO);
    }

    public function error(\Exception $e)
    {
        $this->write($e, LOG_ERR);
    }

    public function debug(\Exception $e)
    {
        $this->write($e, LOG_DEBUG);
    }

    public function add_log(
        $message,
        $function,
        $level = LOG_INFO,
        $projectId = 0,
        $buildId = 0,
        $resourceType = 0,
        $resourceId = 0
    ) {
        add_log($message, $function, $level, $projectId, $buildId, $resourceType, $resourceId);
    }
}
