<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';

class RegisterUserTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
    }

    public function testRegisterUser()
    {
        $client = new GuzzleHttp\Client(['cookies' => true]);
        try {
            // Get the CSRF token.
            $response = $client->request('GET', "{$this->url}/register");
            $html = "{$response->getBody()}";
            $pattern = '/<meta name="csrf-token" content="(\w+)"/';
            $matches = [];
            if (preg_match($pattern, $html, $matches) !== 1) {
                $this->fail('Failed to find CSRF token');
                return;
            }
            $token = $matches[1];

            // POST to /register to create a new user.
            $response = $client->request('POST',
                $this->url . '/register',
                ['form_params' => [
                    '_token' => "{$token}",
                    'fname' => 'Temp',
                    'lname' => 'Testuser',
                    'email' => 'temp@testuser.com',
                    'password' => 'temptestuser',
                    'password_confirmation' => 'temptestuser',
                    'institution' => 'Test Users',
                    'url' => 'catchbot',
                    'sent' => 'Register',
                ]]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }

        // Make sure the user was created successfully.
        if (!$this->userExists('temp@testuser.com')) {
            $this->fail('Failed to register new user');
        }
    }
}
