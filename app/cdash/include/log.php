<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

require_once 'include/defines.php';
require_once 'include/pdo.php';

use CDash\Config;

use \Monolog\Handler\StreamHandler;
use \Monolog\Handler\SyslogHandler;
use \Monolog\Logger;
use \Monolog\Registry;
use \Psr\Log\LogLevel;

function cdash_unlink($filename)
{
    unlink($filename);

    if (file_exists($filename)) {
        throw new Exception("file still exists after unlink: $filename");
    }

    return true;
}

function to_psr3_level($type)
{
    if (is_string($type) && defined('LogLevel::' . strtoupper($type))) {
        return $type;
    }

    switch ($type) {
        case LOG_EMERG:
            $level = LogLevel::EMERGENCY;
            break;
        case LOG_ALERT:
            $level = LogLevel::ALERT;
            break;
        case LOG_CRIT:
            $level = LogLevel::CRITICAL;
            break;
        case LOG_ERR:
            $level = LogLevel::ERROR;
            break;
        case LOG_WARNING:
            $level = LogLevel::WARNING;
            break;
        case LOG_NOTICE:
            $level = LogLevel::NOTICE;
            break;
        case LOG_INFO:
            $level = LogLevel::INFO;
            break;
        case LOG_DEBUG:
            $level = LogLevel::DEBUG;
            break;
        default:
            $level = LogLevel::INFO;
    }
    return $level;
}

/** Add information to the log file */
function add_log($text, $function, $type = LOG_INFO, $projectid = 0, $buildid = 0,
                 $resourcetype = 0, $resourceid = 0)
{
    $config = Config::getInstance();

    $logFile = $config->get('CDASH_LOG_FILE');

    $level = to_psr3_level($type);

    if (($buildid === 0 || is_null($buildid)) && isset($GLOBALS['PHP_ERROR_BUILD_ID'])) {
        $buildid = $GLOBALS['PHP_ERROR_BUILD_ID'];
    }

    $context = array('function' => $function);

    if ($projectid !== 0 && !is_null($projectid)) {
        $context['project_id'] = $projectid;
    }

    if ($buildid !== 0 && !is_null($buildid)) {
        $context['build_id'] = strval($buildid);
    }

    if ($resourcetype !== 0 && !is_null($resourcetype)) {
        $context['resource_type'] = $resourcetype;
    }

    if ($resourceid !== 0 && !is_null($resourceid)) {
        $context['resource_id'] = $resourceid;
    }

    $minLevel = to_psr3_level($config->get('CDASH_LOG_LEVEL'));

    if (!is_null($logFile)) {
        // If the size of the log file is bigger than 10 times the allocated memory
        // we rotate
        $logFileMaxSize = $config->get('CDASH_LOG_FILE_MAXSIZE_MB') * 100000;
        if (file_exists($logFile) && filesize($logFile) > $logFileMaxSize) {
            $tempLogFile = $logFile . '.tmp';
            if (!file_exists($tempLogFile)) {
                rename($logFile, $tempLogFile); // This should be quick so we can keep logging
                for ($i = 9; $i >= 0; $i--) {
                    // If we do not have compression we just rename the files
                    if (function_exists('gzwrite') === false) {
                        $currentLogFile = $logFile . '.' . $i;
                        $j = $i + 1;
                        $newLogFile = $logFile . '.' . $j;
                        if (file_exists($newLogFile)) {
                            cdash_unlink($newLogFile);
                        }
                        if (file_exists($currentLogFile)) {
                            rename($currentLogFile, $newLogFile);
                        }
                    } else {
                        $currentLogFile = $logFile . '.' . $i . '.gz';
                        $j = $i + 1;
                        $newLogFile = $logFile . '.' . $j . '.gz';
                        if (file_exists($newLogFile)) {
                            cdash_unlink($newLogFile);
                        }
                        if (file_exists($currentLogFile)) {
                            $gz = gzopen($newLogFile, 'wb');
                            $f = fopen($currentLogFile, 'rb');
                            while ($f && !feof($f)) {
                                gzwrite($gz, fread($f, 8192));
                            }
                            fclose($f);
                            unset($f);
                            gzclose($gz);
                            unset($gz);
                        }
                    }
                }
                // Move the current backup
                if (function_exists('gzwrite') === false) {
                    rename($tempLogFile, $logFile . '.0');
                } else {
                    $gz = gzopen($logFile . '.0.gz', 'wb');
                    $f = fopen($tempLogFile, 'rb');
                    while ($f && !feof($f)) {
                        gzwrite($gz, fread($f, 8192));
                    }
                    fclose($f);
                    unset($f);
                    gzclose($gz);
                    unset($gz);
                    cdash_unlink($tempLogFile);
                }
            }
        }

        $pid = getmypid();

        if ($pid !== false) {
            $context['pid'] = getmypid();
        }
    }

    if (Registry::hasLogger('cdash') === false) {
        if ($logFile === false) {
            $handler = new SyslogHandler('cdash', LOG_USER, $minLevel);
            $handler->getFormatter()->ignoreEmptyContextAndExtra();
        } else {
            if ($config->get('CDASH_TESTING_MODE')) {
                $filePermission = 0666;
            } else {
                $filePermission = 0664;
            }
            $handler = new StreamHandler($logFile, $minLevel, true,
                $filePermission);
            $handler->getFormatter()->allowInlineLineBreaks();
            $handler->getFormatter()->ignoreEmptyContextAndExtra();
        }

        $logger = new Logger('cdash');
        $logger->pushHandler($handler);
        Registry::addLogger($logger);
    } else {
        $logger = Registry::getInstance('cdash');
    }

    $logger->log($level, $text, $context);
}

function begin_timer($context)
{
    global $cdash_timer_stack;
    if (!isset($cdash_timer_stack)) {
        $cdash_timer_stack = array();
    }

    $timer_entry = array();
    $timer_entry[] = $context;
    $timer_entry[] = microtime_float();

    $cdash_timer_stack[] = $timer_entry;
}

function end_timer($context, $threshold = -0.001)
{
    global $cdash_timer_stack;
    if (!isset($cdash_timer_stack)) {
        trigger_error(
            'end_timer called before begin_timer',
            E_USER_WARNING);
    }

    $end_time = microtime_float();

    $n = count($cdash_timer_stack) - 1;
    $timer_entry = $cdash_timer_stack[$n];
    $begin_context = $timer_entry[0];
    $begin_time = $timer_entry[1];

    if ($context != $begin_context) {
        trigger_error(
            'end_timer called with different context than begin_timer',
            E_USER_WARNING);
    }

    array_pop($cdash_timer_stack);

    $text = '';
    for ($i = 0; $i < $n; ++$i) {
        $text .= '  ';
    }

    $delta = $end_time - $begin_time;

    $text .= $context . ', ' . round($delta, 3) . ' seconds';

    if ($delta > $threshold) {
        add_log($text, 'end_timer');
    }
}
