<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class CreateProjectPermissionsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->BuildId = null;
    }

    public function testCreateProjectPermissions()
    {
        // Test the unauthenticated case.
        $response = $this->get($this->url . '/api/v1/createProject.php');
        $response = json_decode($response);
        $this->assertTrue(property_exists($response, 'requirelogin'));
        $response = $this->get($this->url . '/api/v1/createProject.php?projectid=5');
        $response = json_decode($response);
        $this->assertTrue(property_exists($response, 'requirelogin'));

        // Tests for global administrator.
        $this->login();

        // Can create project.
        $response = $this->get($this->url . '/api/v1/createProject.php');
        $response = json_decode($response);
        $this->assertFalse(property_exists($response, 'error'));

        // Can edit project.
        $response = $this->get($this->url . '/api/v1/createProject.php?projectid=5');
        $response = json_decode($response);
        $this->assertFalse(property_exists($response, 'error'));

        // Emits an error for invalid projectid.
        $response = $this->get($this->url . '/api/v1/createProject.php?projectid=0');
        $response = json_decode($response);
        $this->assertTrue(property_exists($response, 'error'));

        // Tests for normal user.
        $this->logout();
        $this->login('user1@kw', 'user1');

        // Cannot create project.
        $response = $this->get($this->url . '/api/v1/createProject.php');
        $response = json_decode($response);
        $this->assertTrue(property_exists($response, 'error'));

        // Cannot edit project.
        $response = $this->get($this->url . '/api/v1/createProject.php?projectid=5');
        $response = json_decode($response);
        $this->assertTrue(property_exists($response, 'error'));

        // Test for project administrator.
        $pdo = get_link_identifier()->getPdo();
        $user_table = qid('user');
        $stmt = $pdo->prepare("SELECT id FROM $user_table WHERE email=?");
        $stmt->execute(['user1@kw']);
        $row = $stmt->fetch();
        $userid = $row['id'];
        $pdo->exec("DELETE FROM user2project WHERE userid=$userid");
        $pdo->exec("INSERT INTO user2project (userid, projectid, role) VALUES ($userid, 5, 2)");

        // Cannot create project.
        $response = $this->get($this->url . '/api/v1/createProject.php');
        $response = json_decode($response);
        $this->assertTrue(property_exists($response, 'error'));

        // Can edit your own project.
        $response = $this->get($this->url . '/api/v1/createProject.php?projectid=5');
        $response = json_decode($response);
        $this->assertFalse(property_exists($response, 'error'));
        $username = 'user1@kw';
        $password = 'user1';
        $settings = ['Id' => 5, 'Description' => 'This is a new desc'];
        $this->createProject($settings, true, $username, $password);
        // Revert description back to its original value.
        $settings = ['Id' => 5, 'Description' => 'Project Insight test for cdash testing'];
        $this->createProject($settings, true, $username, $password);

        // Cannot edit other projects.
        $response = $this->get($this->url . '/api/v1/createProject.php?projectid=4');
        $response = json_decode($response);
        $this->assertTrue(property_exists($response, 'error'));

        $pdo->exec("DELETE FROM user2project WHERE userid=$userid");
    }
}
