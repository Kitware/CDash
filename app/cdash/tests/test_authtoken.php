<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';



use App\Models\AuthToken;
use App\Services\AuthTokenService;
use CDash\Model\Project;
use CDash\Model\UserProject;

class AuthTokenTestCase extends KWWebTestCase
{
    private $project = 'AuthTokenProject';

    private $Token;
    private $PostBuildId;
    private $Project;
    private $Hash;
    private $PDO;

    public function __construct()
    {
        parent::__construct();
        $this->Hash = '';
        $this->PDO = get_link_identifier()->getPdo();
        $this->PostBuildId = 0;
        $this->Project = null;
        $this->Token = '';
    }

    public function __destruct()
    {
        if ($this->Project) {
            remove_project_builds($this->Project->Id);
            $this->Project->Delete();
        }
    }

    public function testEnableAuthenticatedSubmissions()
    {
        // Login as admin.
        $this->login();

        // Create project.
        $settings = [
            'Name' => 'AuthTokenProject',
            'AuthenticateSubmissions' => true,
            'Public' => 0,
        ];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
        }
        $this->Project = new Project();
        $this->Project->Id = $projectid;

        // Subscribe a non-administrative user to it.
        $stmt = $this->PDO->query(
            'SELECT * FROM ' . qid('user') . " WHERE email = 'user1@kw'");
        $row = $stmt->fetch();
        if (!$row) {
            $this->fail('Failed to find non-admin user');
        }
        $userproject = new UserProject();
        $userproject->ProjectId = $projectid;
        $userproject->UserId = $row['id'];
        if (!$userproject->Save()) {
            $this->fail('Failed to assign user to project');
        }
    }

    public function testGenerateToken()
    {
        // Log in as non-admin user.
        $this->login('user1@kw', 'user1');

        // Use API to generate token.
        $response = $this->post($this->url . '/api/authtokens/create', ['description' => 'mytoken', 'scope' => AuthToken::SCOPE_FULL_ACCESS]);
        $response = json_decode($response, true);
        if (!array_key_exists('raw_token', $response)) {
            $this->fail('Failed to generate token');
        }
        $this->Token = $response['raw_token'];

        // Test that the model agrees that this token exists.
        $tokenmodel = new AuthToken();
        $this->Hash = $response['token']['hash'];
        $tokenmodel->Hash = $this->Hash;
        if (!$tokenmodel->Exists()) {
            $this->fail('Token does not exist');
        }
    }

    public function testApiAccess()
    {
        $client = new GuzzleHttp\Client(['cookies' => true]);
        $client->getConfig('cookies')->clear();

        // Make sure we can't visit the private project page
        // without being logged in.
        $exception_thrown = false;
        try {
            $response = $client->request('GET',
                $this->url . '/api/v1/index.php?project=AuthTokenProject');
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $exception_thrown = true;
            $status_code = $e->getResponse()->getStatusCode();
            if ($status_code != 401) {
                $this->fail("Expected 401 but got $status_code");
            }
        }
        if (!$exception_thrown) {
            $this->fail('No Exception thrown for unauthenticated index.php');
        }

        // Let's try that request again, but this time we specify a valid
        // bearer token.
        try {
            $response = $client->request('GET',
                $this->url . '/api/v1/index.php?project=AuthTokenProject',
                [ 'headers' => ['Authorization' => "Bearer $this->Token"] ]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return 1;
        }

        if (!$response) {
            $this->fail('No response from request');
            return 1;
        }

        $status_code = $response ? $response->getStatusCode() : null;
        $this->assertEqual(200, $status_code);

        $response_array = json_decode($response->getBody(), true);
        $this->assertEqual('AuthTokenProject', $response_array['title']);
    }

    public function testSubmissionWorksWithToken()
    {
        // Make sure various submission paths are successful when we present
        // our authentication token.
        $headers = ["Authorization: Bearer {$this->Token}"];
        if (!$this->normalSubmit($headers)) {
            $this->fail('Normal submit failed using token');
        }
        if (!$this->postSubmit($this->Token)) {
            $this->fail('POST submit failed using token');
        }
        if (!$this->putSubmit($headers)) {
            $this->fail('PUT submit failed using token');
        }
    }

    public function normalSubmit($headers)
    {
        $file = dirname(__FILE__) . '/data/InsightExperimentalExample/Insight_Experimental_Build.xml';
        $url = "{$this->url}/submit.php?project=AuthTokenProject";
        $result = $this->uploadfile($url, $file, $headers);
        if (!$result || strpos($result, '<status>OK</status>') === false) {
            return false;
        }
        return true;
    }

    public function postSubmit($token)
    {
        $post_params = [
            'project' => 'AuthTokenProject',
            'build' => 'token_test',
            'site' => 'localhost',
            'stamp' => '20161004-0500-Nightly',
            'starttime' => '1475599870',
            'endtime' => '1475599870',
            'track' => 'Nightly',
            'type' => 'GcovTar',
            'datafilesmd5[0]=' => '5454e16948a1d58d897e174b75cc5633',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->url}/submit.php");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        }

        // Parse buildid for subsequent PUT attempts.
        $response = curl_exec($ch);
        curl_close($ch);
        $response_array = json_decode($response, true);
        if (!is_array($response_array)) {
            return false;
        }
        $this->PostBuildId = $response_array['buildid'];
        return true;
    }

    public function putSubmit($headers)
    {
        $file = dirname(__FILE__) . '/data/gcov.tar';
        $puturl = $this->url . "/submit.php?type=GcovTar&md5=5454e16948a1d58d897e174b75cc5633&filename=gcov.tar&buildid={$this->PostBuildId}";
        $put_result = $this->uploadfile($puturl, $file, $headers);
        if (strpos($put_result, '{"status":0}') === false) {
            return false;
        }
        return true;
    }

    public function testSubmissionFailsWithInvalidToken()
    {
        // Revoke user's access to this project.
        $this->PDO->query("DELETE FROM user2project WHERE projectid = {$this->Project->Id}");

        // Make sure the various submission paths fail for our token now.
        $headers = ["Authorization: Bearer {$this->Token}"];
        if ($this->normalSubmit($headers)) {
            $this->fail('Normal submit succeeded for invalid token');
        }
        if ($this->postSubmit($this->Token)) {
            $this->fail('POST submit succeeded for invalid token');
        }
        if ($this->putSubmit($headers)) {
            $this->fail('PUT submit succeeded for invalid token');
        }
    }

    public function testSubmissionFailsWithoutToken()
    {
        // Make sure submission fails when no token is presented.
        if ($this->normalSubmit([])) {
            $this->fail('Normal submit succeeded without token');
        }
        if ($this->postSubmit('')) {
            $this->fail('POST submit succeeded without token');
        }
        if ($this->putSubmit([])) {
            $this->fail('PUT submit succeeded without token');
        }
    }

    public function testRevokeToken()
    {
        // Log in as non-admin user.
        $this->login('user1@kw', 'user1');

        // Use API to revoke token.
        $this->delete($this->url . "/api/authtokens/delete/$this->Hash");

        // Make sure the token is really gone.
        if (AuthToken::find($this->Hash)) {
            $this->fail('Token still exists after it was revoked');
        }
    }

    public function testRemoveExpiredToken()
    {
        // Put an expired token in the database.
        $result = AuthTokenService::generateToken(1, -1, AuthToken::SCOPE_FULL_ACCESS, "Test Token 1");
        $token = $result['raw_token'];
        $authtoken = $result['token'];
        $authtoken['expires'] = gmdate(FMT_DATETIME, 1);
        $authtoken->save();

        // Try to submit using this token.
        // This will cause it to be revoked since it has already expired.
        $headers = ["Authorization: Bearer {$token}"];
        if ($this->normalSubmit($headers)) {
            $this->fail("Normal submit succeeded with an expired token");
        }

        // Make sure this token does not exist anymore.
        if (AuthToken::find($this->Hash)) {
            $this->fail("Expired token still exists after submission");
        }
    }
}
