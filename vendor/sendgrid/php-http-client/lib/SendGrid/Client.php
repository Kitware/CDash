<?php
/**
  * HTTP Client library
  *
  * PHP version 5.2
  *
  * @author    Matt Bernier <dx@sendgrid.com>
  * @author    Elmer Thomas <dx@sendgrid.com>
  * @copyright 2016 SendGrid
  * @license   https://opensource.org/licenses/MIT The MIT License
  * @version   GIT: <git_id>
  * @link      http://packagist.org/packages/sendgrid/php-http-client
  */
namespace SendGrid;

/**
  * Holds the response from an API call.
  */
class Response
{
    /**
      * Setup the response data
      *
      * @param int   $status_code      the status code.
      * @param array $response_body    the response body as an array.
      * @param array $response_headers an array of response headers.
      */
    function __construct($status_code = null, $response_body = null, $response_headers = null)
    {
        $this->_status_code = $status_code;
        $this->_body = $response_body;
        $this->_headers = $response_headers;
    }

    /**
    * The status code
    *
    * @return integer
    */
    public function statusCode()
    {
        return $this->_status_code;
    }

    /**
    * The response body
    *
    * @return array
    */
    public function body()
    {
        return $this->_body;
    }

    /**
    * The response headers
    *
    * @return array
    */
    public function headers()
    {
        return $this->_headers;
    }
}

/**
  * Quickly and easily access any REST or REST-like API.
  */
class Client
{

    public
      $host,
      $request_headers,
      $version,
      $url_path,
      $methods;

    /**
      * Initialize the client
      *
      * @param string $host            the base url (e.g. https://api.sendgrid.com)
      * @param array  $request_headers global request headers
      * @param string $version         api version (configurable)
      * @param array  $url_path        holds the segments of the url path
      */
    function __construct($host, $request_headers = null, $version = null, $url_path = null)
    {
        $this->host = $host;
        $this->request_headers = ($request_headers ? $request_headers : []);
        $this->version = $version;
        $this->url_path = ($url_path ? $url_path : []);
        // These are the supported HTTP verbs
        $this->methods = ['delete', 'get', 'patch', 'post', 'put'];
    }

    /**
      * Make a new Client object
      *
      * @param string $name name of the url segment
      *
      * @return Client object
      */
    private function _buildClient($name = null)
    {
        if(isset($name)) {
            array_push($this->url_path, $name);
        }
        $url_path = $this->url_path;
        $this->url_path = [];
        return new Client($this->host, $this->request_headers, $this->version, $url_path);
    }

    /**
      * Subclass this function for your own needs.
      *  Or just pass the version as part of the URL
      *  (e.g. client._('/v3'))
      *
      * @param string $url URI portion of the full URL being requested
      *
      * @return string
    */
    private function _buildVersionedUrl($url)
    {
        return sprintf("%s%s%s", $this->host, $this->version, $url);
    }

    /**
      * Build the final URL to be passed
      *
      * @param array $query_params an array of all the query parameters
      *
      * @return string
      */
    private function _buildUrl($query_params = null)
    {
        $url = '/'.implode('/', $this->url_path);
        if (isset($query_params)) {
            $url_values = http_build_query($query_params);
            $url = sprintf('%s?%s', $url, $url_values);
        }
        if (isset($this->version)) {
            $url = $this->_buildVersionedUrl($url);
        } else {
            $url = sprintf('%s%s', $this->host, $url);;
        }
        return $url;
    }

    /**
      * Make the API call and return the response. This is separated into
      * it's own function, so we can mock it easily for testing.
      *
      * @param array  $method          the HTTP verb
      * @param string $url             the final url to call
      * @param array  $request_body    request body
      * @param array  $request_headers any additional request headers
      *
      * @return Response object
      */
    public function makeRequest($method, $url, $request_body = null, $request_headers = null)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        if(isset($request_headers)) {
            $this->request_headers = array_merge($this->request_headers, $request_headers);
        }
        if(isset($request_body)) {
            $request_body = json_encode($request_body);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
            $content_length = array('Content-Length: ' . strlen($request_body));
            $content_type = array('Content-Type: application/json');
            $this->request_headers = array_merge($this->request_headers, $content_type);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->request_headers);
        $curl_response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $response_body = substr($curl_response, $header_size);
        $response_header = substr($curl_response, 0, $header_size);

        curl_close($curl);

        return new Response($status_code, $response_body, $response_header);
    }

    /**
      * Add variable values to the url.
      * (e.g. /your/api/{variable_value}/call)
      * Another example: if you have a PHP reserved word, such as and,
      * in your url, you must use this method.
      *
      * @param string $name name of the url segment
      *
      * @return Client object
      */
    public function _($name = null)
    {
        return $this->_buildClient($name);
    }

    /**
      * Dynamically add method calls to the url, then call a method.
      * (e.g. client.name.name.method())
      *
      * @param string $name name of the dynamic method call or HTTP verb
      * @param array  $args parameters passed with the method call
      *
      * @return Client or Response object
      */
    public function __call($name, $args)
    {
        if($name == 'version') {
            $this->version = $args[0];
            return $this->_();
        }

        if (in_array($name, $this->methods)) {
            $query_params = ((count($args) >= 2) ? $args[1] : null);
            $url = $this->_buildUrl($query_params);
            $request_body = ($args ? $args[0] : null);
            $request_headers = ((count($args) == 3) ? $args[2] : null);
            return $this->makeRequest($name, $url, $request_body, $request_headers);
        }

        return $this->_($name);
    }
}
?>
