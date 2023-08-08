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



use Illuminate\Support\Facades\Log;
use \Psr\Log\LogLevel;

if (!function_exists('cdash_unlink')) {
    function cdash_unlink($filename)
    {
        unlink($filename);

        if (file_exists($filename)) {
            throw new Exception("file still exists after unlink: $filename");
        }

        return true;
    }
}

if (!function_exists('to_psr3_level')) {
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
}

if (!function_exists('add_log')) {
    /**
     * Add information to the log file
     *
     * @deprecated 04/04/2023  Use \Illuminate\Support\Facades\Log for logging instead
     */
    function add_log($text, $function, $type = LOG_INFO, $projectid = 0, $buildid = 0,
        $resourcetype = 0, $resourceid = 0)
    {
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

        Log::log($level, $text, $context);
    }
}
