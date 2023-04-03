<?php
namespace CDash;

require_once 'include/log.php';

class Log extends Singleton
{
    /**
     * @deprecated 04/04/2023  Use \Illuminate\Support\Facades\Log for logging instead
     */
    protected function write(\Exception $e, $level)
    {
        $message = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
        $trace = $e->getTrace();
        $function = $trace[0]['function'];
        add_log($message, $function, $level);
    }

    /**
     * @deprecated 04/04/2023  Use \Illuminate\Support\Facades\Log for logging instead
     */
    public function info(\Exception $e)
    {
        $this->write($e, LOG_INFO);
    }

    /**
     * @deprecated 04/04/2023  Use \Illuminate\Support\Facades\Log for logging instead
     */
    public function error(\Exception $e)
    {
        $this->write($e, LOG_ERR);
    }

    /**
     * @deprecated 04/04/2023  Use \Illuminate\Support\Facades\Log for logging instead
     */
    public function debug(\Exception $e)
    {
        $this->write($e, LOG_DEBUG);
    }

    /**
     * @deprecated 04/04/2023  Use \Illuminate\Support\Facades\Log for logging instead
     */
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
