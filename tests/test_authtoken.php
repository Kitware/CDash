<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\AuthToken;
use CDash\Model\Project;
use CDash\Model\UserProject;

class AuthTokenTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ConfigLine = '$CDASH_DEFAULT_AUTHENTICATE_SUBMISSIONS = 1;';
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

        // Verify setting is off by default.
        $response = $this->get($this->url . '/api/v1/createProject.php');
        $response = json_decode($response, true);
        $this->assertEqual($response['project']['AuthenticateSubmissions'], 0);

        // Enable config setting.
        $this->addLineToConfig($this->ConfigLine);

        // Verify setting is now on by default.
        $response = $this->get($this->url . '/api/v1/createProject.php');
        $response = json_decode($response, true);
        $this->assertEqual($response['project']['AuthenticateSubmissions'], 1);

        // Create project.
        $settings = [
            'Name' => 'AuthTokenProject',
            'AuthenticateSubmissions' => 1,
            'Public' => 0
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

        // Disable config setting.
        $this->removeLineFromConfig($this->ConfigLine);
    }

    public function testGenerateToken()
    {
        // Log in as non-admin user.
        $this->login('user1@kw', 'user1');

        // Use API to generate token.
        $response =
            $this->post($this->url . '/api/v1/authtoken.php', ['description' => 'mytoken']);
        $response = json_decode($response, true);
        if (!array_key_exists('token', $response)) {
            $this->fail('Failed to generate token');
        }
        if (!array_key_exists('token', $response['token'])) {
            $this->fail('Failed to generate access token');
        }
        $this->Token = $response['token']['token'];

        // Test that the model agrees that this token exists.
        $tokenmodel = new AuthToken();
        $this->Hash = $response['token']['hash'];
        $tokenmodel->Hash = $this->Hash;
        if (!$tokenmodel->Exists()) {
            $this->fail('Token does not exist');
        }
    }

    public function testSubmissionWorksWithToken()
    {
        // Make sure various submission paths are successful when we present
        // our authentication token.
        $headers = ["Authorization: Bearer $this->Token"];
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

    public function testSubmissionFailsWithInvalidToken()
    {
        // Revoke user's access to this project.
        $this->PDO->query("DELETE FROM user2project WHERE projectid = {$this->Project->Id}");

        // Make sure the various submission paths fail for our token now.
        $headers = ["Authorization: Bearer $this->Token"];
        if ($this->normalSubmit($headers)) {
            $this->fail('Normal submit succeeded for invalid user');
        }
        if ($this->postSubmit($this->Token)) {
            $this->fail('POST submit succeeded for invalid user');
        }
        if ($this->putSubmit($headers)) {
            $this->fail('PUT submit succeeded for invalid user');
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

    public function normalSubmit($headers)
    {
        $xml = dirname(__FILE__) . '/data/InsightExperimentalExample/Insight_Experimental_Build.xml';
        return $this->submission('AuthTokenProject', $xml, $headers);
    }

    public function postSubmit($token)
    {
        $fields = [
            'project' => 'AuthTokenProject',
            'build' => 'token_test',
            'site' => 'localhost',
            'stamp' => '20161004-0500-Nightly',
            'starttime' => '1475599870',
            'endtime' => '1475599870',
            'track' => 'Nightly',
            'type' => 'GcovTar',
            'datafilesmd5[0]=' => '5454e16948a1d58d897e174b75cc5633'];

        $headers = [];
        if ($token) {
            $headers = [ 'Authorization' => "Bearer $token" ];
        }

        $client = new GuzzleHttp\Client();
        global $CDASH_BASE_URL;
        try {
            $response = $client->request(
                'POST',
                $CDASH_BASE_URL . '/submit.php',
                [
                    'form_params' => $fields,
                    'headers' => $headers
                ]
            );
        } catch (GuzzleHttp\Exception\ClientException $e) {
            return false;
        }

        // Parse buildid for subsequent PUT attempts.
        $response_array = json_decode($response->getBody(), true);
        $this->PostBuildId = $response_array['buildid'];
        return true;
    }

    public function putSubmit($headers)
    {
        $puturl = $this->url . "/submit.php?type=GcovTar&md5=5454e16948a1d58d897e174b75cc5633&filename=gcov.tar&buildid=$this->PostBuildId";
        $filename = dirname(__FILE__) . '/data/gcov.tar';
        return $this->uploadfile($puturl, $filename, $headers);
    }

    public function testRevokeToken()
    {
        // Log in as non-admin user.
        $this->login('user1@kw', 'user1');

        // Use API to revoke token.
        $this->delete($this->url . "/api/v1/authtoken.php?hash=$this->Hash");

        // Make sure the token is really gone.
        $tokenmodel = new AuthToken();
        $tokenmodel->Hash = $this->Hash;
        if ($tokenmodel->Exists()) {
            $this->fail('Token still exists after it was revoked');
        }
    }

    public function testRemoveExpiredToken()
    {
        // Put an expired token in the database.
        $authtoken = new AuthToken();
        $token = $authtoken->Generate();
        $authtoken->UserId = 1;
        $authtoken->Expires = gmdate(FMT_DATETIME, 1);
        $authtoken->Save();

        // Try to submit using this token.
        // This will cause it to be revoked since it has already expired.
        $headers = ["Authorization: Bearer $token"];
        if ($this->normalSubmit($headers)) {
            $this->fail("Normal submit succeeded with an expired token");
        }

        // Make sure this token does not exist anymore.
        if ($authtoken->Exists()) {
            $this->fail("Expired token still exists after submission");
        }
    }
}
