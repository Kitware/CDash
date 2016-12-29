<?php
class MockClient extends SendGrid\Client
{
    public 
      $request_body,
      $request_headers,
      $url;
    
    public function makeRequest($method, $url, $request_body = null, $request_headers = null)
    {
        $this->request_body = $request_body;
        $this->request_headers = $request_headers;
        $this->url = $url;
        return $this;
    }
}

class ClientTest_Client extends PHPUnit_Framework_TestCase
{
    protected 
      $client,
      $host,
      $headers;
    
    protected function setUp()
    {
        $this->host = 'https://localhost:4010';
        $this->headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer SG.XXXX'
        );
        $this->client = new MockClient($this->host, $this->headers, '/v3', null);
    }
    
    public function testInitialization()
    {
        $this->assertEquals($this->client->host, $this->host);
        $this->assertEquals($this->client->request_headers, $this->headers);
        $this->assertEquals($this->client->version, '/v3');
        $this->assertEquals($this->client->url_path, []);
        $this->assertEquals($this->client->methods, ['delete', 'get', 'patch', 'post', 'put']);
    }
    
    public function test_()
    {
        $client = $this->client->_('test');
        $this->assertEquals($client->url_path, array('test'));
    }
    
    public function test__call()
    {
        $client = $this->client->get();
        $this->assertEquals($client->url, 'https://localhost:4010/v3/');
      
        $query_params = array('limit' => 100, 'offset' => 0);
        $client = $this->client->get(null, $query_params);
        $this->assertEquals($client->url, 'https://localhost:4010/v3/?limit=100&offset=0');
      
        $request_body = array('name' => 'A New Hope');
        $client = $this->client->get($request_body);
        $this->assertEquals($client->request_body, $request_body);
      
        $request_headers = array('X-Mock: 200');
        $client = $this->client->get(null, null, $request_headers);
        $this->assertEquals($client->request_headers, $request_headers);
      
        $client = $this->client->version('/v4');
        $this->assertEquals($client->version, '/v4');
      
        $client = $this->client->path_to_endpoint();
        $this->assertEquals($client->url_path, array('path_to_endpoint'));
        $client = $client->one_more_segment();
        $this->assertEquals($client->url_path, array('path_to_endpoint', 'one_more_segment'));
    }
}
?>