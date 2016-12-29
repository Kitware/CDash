<?php
// If running this outside of this context, use the following include and
// comment out the two includes below
// require __DIR__ . '/vendor/autoload.php';
include(dirname(__DIR__).'/lib/SendGrid/client.php');
// This gets the parent directory, for your current directory use getcwd()
$path_to_config = dirname(__DIR__);
$api_key = getenv('SENDGRID_API_KEY');
$headers = array('Authorization: Bearer '.$api_key);
$client = new SendGrid\Client('https://api.sendgrid.com', $headers, '/v3', null);

// GET Collection
$query_params = array('limit' => 100, 'offset' => 0);
$request_headers = array('X-Mock: 200');
$response = $client->api_keys()->get(null, $query_params, $request_headers);
echo $response->statusCode();
echo $response->body();
echo $response->headers();

// POST
$request_body = array(
        'name' => 'My PHP API Key',
        'scopes' => array(
            'mail.send',
            'alerts.create',
            'alerts.read'
        )
);
$response = $client->api_keys()->post($request_body);
echo $response->statusCode();
echo $response->body();
echo $response->headers();
$response_body = json_decode($response->body());
$api_key_id = $response_body->api_key_id;

// GET Single
$response = $client->version('/v3')->api_keys()->_($api_key_id)->get();
echo $response->statusCode();
echo $response->body();
echo $response->headers();

// PATCH
$request_body = array(
        'name' => 'A New Hope'
);
$response = $client->api_keys()->_($api_key_id)->patch($request_body);
echo $response->statusCode();
echo $response->body();
echo $response->headers();

// PUT
$request_body = array(
        'name' => 'A New Hope',
        'scopes' => array(
            'user.profile.read',
            'user.profile.update'
        )
);
$response = $client->api_keys()->_($api_key_id)->put($request_body);
echo $response->statusCode();
echo $response->body();
echo $response->headers();

// DELETE
$response = $client->api_keys()->_($api_key_id)->delete();
echo $response->statusCode();
echo $response->body();
echo $response->headers();

?>