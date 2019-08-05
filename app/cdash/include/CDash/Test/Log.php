<?php
namespace CDash\Test;

/**
 * Class Log
 * @package CDash\Test
 *
 * All writes are stored in the member variable $log and can be accessed to ensure that things are
 * being logged properly without actually writing to disk then having to open up and read a file.
 */
class Log extends \CDash\Log
{
    /** @var array  */
    private $log;

    /**
     * Log constructor
     */
    public function __construct()
    {
        $this->log = [];
    }

    /**
     * @param \Exception $e
     * @param $level
     */
    protected function write(\Exception $e, $level)
    {
        $this->log[] = [
            'message' => $e->getMessage(),
            'level' => $level,
        ];
    }

    /**
     * @return array
     */
    public function getLogEntries()
    {
        return $this->log;
    }

    /**
     * @return void;
     */
    public function clear()
    {
        $this->log = [];
    }
}
