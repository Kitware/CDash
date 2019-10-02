<?php

namespace IronCore;

/**
 * Core functionality for Iron.io products
 *
 * @link https://github.com/iron-io/iron_core_php
 * @link http://www.iron.io/
 * @link http://dev.iron.io/
 * @version 1.0.2
 * @package IronCore
 * @copyright BSD 2-Clause License. See LICENSE file.
 */
class IronCore
{
    protected $core_version = '1.0.2';

    // should be overridden by child class
    protected $client_version = null;
    protected $client_name    = null;
    protected $product_name   = null;
    protected $default_values = null;

    const HTTP_OK       = 200;
    const HTTP_CREATED  = 201;
    const HTTP_ACCEPTED = 202;

    const POST   = 'POST';
    const PUT    = 'PUT';
    const GET    = 'GET';
    const DELETE = 'DELETE';
    const PATCH  = 'PATCH';

    const HEADER_ACCEPT          = "application/json";
    const HEADER_ACCEPT_ENCODING = "gzip, deflate";

    protected $url;
    protected $token;
    protected $api_version;
    protected $version;
    protected $project_id;
    protected $headers;
    protected $protocol;
    protected $host;
    protected $port;
    protected $encryption_key;
    protected $curl = null;
    protected $last_status;

    protected $urlFetchContext;
    protected $urlFetchData;
    protected $urlFetchUrl;

    public $max_retries        = 5;
    public $debug_enabled      = false;
    public $ssl_verifypeer     = true;
    public $connection_timeout = 60;
    public $execute_timeout    = 60;
    public $proxy              = null;
    public $proxy_userpwd      = null;

    public function __destruct()
    {
        if ($this->curl != null) {
            curl_close($this->curl);
            $this->curl = null;
        }
    }

    public function getLastStatus()
    {
        return $this->last_status;
    }

    protected static function dateRfc3339($timestamp = 0)
    {
        if ($timestamp instanceof \DateTime) {
            $timestamp = $timestamp->getTimestamp();
        }
        if (!$timestamp) {
            $timestamp = time();
        }

        return gmdate('c', $timestamp);
    }

    protected static function json_decode($response)
    {
        $data = json_decode($response);
        if (function_exists('json_last_error')) {
            $json_error = json_last_error();
            if ($json_error != JSON_ERROR_NONE) {
                throw new JSONException($json_error);
            }
        } elseif ($data === null) {
            throw new JSONException("Common JSON error");
        }

        return $data;
    }

    protected static function homeDir()
    {
        if ($home_dir = getenv('HOME')) {
            // *NIX
            return $home_dir . DIRECTORY_SEPARATOR;
        } else {
            // Windows
            return getenv('HOMEDRIVE') . getenv('HOMEPATH') . DIRECTORY_SEPARATOR;
        }
    }

    protected function debug($var_name, $variable)
    {
        if ($this->debug_enabled) {
            echo "{$var_name}: " . var_export($variable, true) . "\n";
        }
    }

    protected function userAgent()
    {
        return "{$this->client_name}-{$this->client_version} (iron_core-{$this->core_version})";
    }

    /**
     * Load configuration
     *
     * @param array|string|null $config
     * array of options or name of config file
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function getConfigData($config)
    {
        if (is_string($config)) {
            if (!file_exists($config)) {
                throw new \InvalidArgumentException("Config file $config not found");
            }
            $this->loadConfigFile($config);
        } elseif (is_array($config)) {
            $this->loadFromHash($config);
        }

        $this->loadConfigFile('iron.ini');
        $this->loadConfigFile('iron.json');

        $this->loadFromEnv(strtoupper($this->product_name));
        $this->loadFromEnv('IRON');

        if (!ini_get('open_basedir')) {
            $this->loadConfigFile(self::homeDir() . '.iron.ini');
            $this->loadConfigFile(self::homeDir() . '.iron.json');
        }

        $this->loadFromHash($this->default_values);

        if (empty($this->token) || empty($this->project_id)) {
            throw new \InvalidArgumentException("token or project_id not found in any of the available sources");
        }
    }

    protected function loadFromHash($options)
    {
        if (empty($options)) {
            return;
        }
        $this->setVarIfValue('token', $options);
        $this->setVarIfValue('project_id', $options);
        $this->setVarIfValue('protocol', $options);
        $this->setVarIfValue('host', $options);
        $this->setVarIfValue('port', $options);
        $this->setVarIfValue('api_version', $options);
        $this->setVarIfValue('encryption_key', $options);
    }

    protected function loadFromEnv($prefix)
    {
        $this->setVarIfValue('token', getenv($prefix . "_TOKEN"));
        $this->setVarIfValue('project_id', getenv($prefix . "_PROJECT_ID"));
        $this->setVarIfValue('protocol', getenv($prefix . "_SCHEME"));
        $this->setVarIfValue('host', getenv($prefix . "_HOST"));
        $this->setVarIfValue('port', getenv($prefix . "_PORT"));
        $this->setVarIfValue('api_version', getenv($prefix . "_API_VERSION"));
        $this->setVarIfValue('encryption_key', getenv($prefix . "_ENCRYPTION_KEY"));
    }

    protected function setVarIfValue($key, $options_or_value)
    {
        if (!empty($this->$key)) {
            return;
        }
        if (is_array($options_or_value)) {
            if (!empty($options_or_value[$key])) {
                $this->$key = $options_or_value[$key];
            }
        } else {
            if (!empty($options_or_value)) {
                $this->$key = $options_or_value;
            }
        }
    }

    protected function loadConfigFile($file)
    {
        if (!file_exists($file)) {
            return;
        }
        $data = @parse_ini_file($file, true);
        if ($data === false) {
            $data = json_decode(file_get_contents($file), true);
        }
        if (!is_array($data)) {
            throw new \InvalidArgumentException("Config file $file not parsed");
        }

        if (!empty($data[$this->product_name])) {
            $this->loadFromHash($data[$this->product_name]);
        }
        if (!empty($data['iron'])) {
            $this->loadFromHash($data['iron']);
        }
        $this->loadFromHash($data);
    }

    protected function apiCall($type, $url, $params = array(), $data = null)
    {
        $url = "{$this->url}$url";
        $this->debug("API $type", $url);

        if ($this->curl == null && $this->curlEnabled()) {
            $this->curl = curl_init();
        }
        if (!isset($params['oauth'])) {
            $params['oauth'] = $this->token;
        }
        if ($this->curlEnabled()) {
            switch ($type) {
                case self::DELETE:
                    curl_setopt($this->curl, CURLOPT_URL, $url);
                    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, self::DELETE);
                    curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($params));
                    break;
                case self::PUT:
                    curl_setopt($this->curl, CURLOPT_URL, $url);
                    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, self::PUT);
                    curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($params));
                    break;
                case self::PATCH:
                    curl_setopt($this->curl, CURLOPT_URL, $url);
                    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, self::PATCH);
                    curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($params));
                    break;
                case self::POST:
                    curl_setopt($this->curl, CURLOPT_URL, $url);
                    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, self::POST);
                    curl_setopt($this->curl, CURLOPT_POST, true);
                    // php 5.6+ requires this for @file style uploads
                    if (!class_exists("\CURLFile") && defined('CURLOPT_SAFE_UPLOAD')) {
                        curl_setopt($this->curl, CURLOPT_SAFE_UPLOAD, false);
                    }
                    if ($data) {
                        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
                    } else {
                        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($params));
                    }
                    break;
                case self::GET:
                    curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
                    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, self::GET);
                    curl_setopt($this->curl, CURLOPT_HTTPGET, true);
                    $url .= '?' . http_build_query($params);
                    curl_setopt($this->curl, CURLOPT_URL, $url);
                    break;
            }

            if (!is_null($this->proxy)) {
                curl_setopt($this->curl, CURLOPT_PROXY, $this->proxy);
                if (!is_null($this->proxy_userpwd)) {
                    curl_setopt($this->curl, CURLOPT_PROXYUSERPWD, $this->proxy_userpwd);
                }
            }
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->compiledCurlHeaders());
            curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->execute_timeout);
        } else {
            $this->debug("Call with URL Fetch", $url);
            if ($type == self::GET) {
                $url .= '?' . http_build_query($params);
                $this->urlFetchUrl = $url;
                $this->urlFetchContext = stream_context_create(array(
                    'http' => array(
                        'method'      => $type,
                        'verify_peer' => $this->ssl_verifypeer,
                        'header'      => $this->compiledUrlFetchHeaders(),
                    ),
                ));
            } else {
                $this->urlFetchUrl = $url;
                $this->urlFetchContext = stream_context_create(array(
                    'http' => array(
                        'method'      => $type,
                        'verify_peer' => $this->ssl_verifypeer,
                        'header'      => $this->compiledUrlFetchHeaders(),
                        'content'     => json_encode($params),
                    ),
                ));
            }
        }

        return $this->callWithRetries();
    }

    protected function callWithRetries()
    {
        for ($retry = 0; $retry < $this->max_retries; $retry++) {
            if ($this->curlEnabled()) {
                $_out = curl_exec($this->curl);
                if ($_out === false) {
                    $this->reportHttpError(0, curl_error($this->curl));
                }
                $this->last_status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            } else {
                try
                {
                    $_out = file_get_contents($this->urlFetchUrl, false, $this->urlFetchContext);
                    $responseHeader = explode(' ', $http_response_header[0]);
                    $this->last_status = $responseHeader[1];
                } catch (\Exception $e) {
                    $this->reportHttpError(0, $e->getMessage());

                    return null;
                }
            }
            switch ($this->last_status) {
                case self::HTTP_OK:
                case self::HTTP_CREATED:
                case self::HTTP_ACCEPTED:
                    return $_out;
                case HttpException::INTERNAL_ERROR:
                    self::waitRandomInterval($retry);
                    break;
                case HttpException::SERVICE_UNAVAILABLE:
                case HttpException::GATEWAY_TIMEOUT:
                    self::waitRandomInterval($retry);
                    break;
                default:
                    $this->reportHttpError($this->last_status, $_out);
            }
        }
        $this->reportHttpError($this->last_status, "Service unavailable");

        return null;
    }

    protected function reportHttpError($status, $text)
    {
        throw new HttpException("http error: {$status} | {$text}", $status);
    }

    protected function curlEnabled()
    {
        return function_exists('curl_version');
    }

    /**
     * Wait for a random time between 0 and (4^currentRetry * 100) milliseconds
     *
     * @static
     *
     * @param int $retry currentRetry number
     */
    protected static function waitRandomInterval($retry)
    {
        $max_delay = pow(4, $retry) * 100 * 1000;
        usleep(rand(0, $max_delay));
    }

    protected function compiledHeaders()
    {
        if ($this->curlEnabled()) {
            return $this->compiledCurlHeaders();
        } else {
            return $this->compiledUrlFetchHeaders();
        }
    }

    protected function compiledCurlHeaders()
    {
        # Set default headers if no headers set.
        if ($this->headers == null) {
            $this->setCommonHeaders();
        }

        $headers = array();
        foreach ($this->headers as $k => $v) {
            $headers[] = "$k: $v";
        }

        return $headers;
    }

    protected function compiledUrlFetchHeaders()
    {
        # Set default headers if no headers set.
        if ($this->headers == null) {
            $this->setCommonHeaders();
        }

        $headers = "";
        foreach ($this->headers as $k => $v) {
            if ($k == 'Connection') {
                $v = 'Close';
            }
            $headers .= "$k: $v\r\n";
        }

        return $headers;
    }

    protected function setCommonHeaders()
    {
        $this->headers = array(
            'Authorization'   => "OAuth {$this->token}",
            'User-Agent'      => $this->userAgent(),
            'Content-Type'    => 'application/json',
            'Accept'          => self::HEADER_ACCEPT,
            'Accept-Encoding' => self::HEADER_ACCEPT_ENCODING,
            'Connection'      => 'Keep-Alive',
            'Keep-Alive'      => '300',
        );
    }
}
