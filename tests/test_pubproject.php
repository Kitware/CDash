<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class PubProjectTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testCreateProject()
    {
        $content = $this->connect($this->url);
        if (!$content) {
            return;
        }
        $this->login();
        if (!$this->analyse($this->clickLink('Create new project'))) {
            return;
        }

        $this->setField('name', 'ProjectTest');
        $this->setField('description', 'This is a project test for cdash');
        $this->setField('public', '1');
        $this->setField('emailAdministrator', '1');
        $this->clickSubmitByName('Submit');

        $query = 'SELECT COUNT(*) FROM project';
        $result = $this->db->query($query);
        if (strcmp($this->db->getType(), 'pgsql') == 0 &&
            $result[0]['count'] < 1
        ) {
            $result = $result[0]['count'];
            $errormsg = "The result of the query '$query' which is $result";
            $errormsg .= 'is not the one expected: 1';
            $this->assertEqual($result, '1', $errormsg);
            return;
        } elseif (strcmp($this->db->getType(), 'mysql') == 0 &&
            $result[0]['COUNT(*)'] < 1
        ) {
            $result = $result[0]['COUNT(*)'];
            $errormsg = "The result of the query '$query' which is $result";
            $errormsg .= 'is not the one expected: 1';
            $this->assertEqual($result, '1', $errormsg);
            return;
        }

        $this->checkErrors();
        $this->assertText('The project ProjectTest has been created successfully.');
    }

    public function testProjectTestInDatabase()
    {
        $query = "SELECT name,description,public FROM project WHERE name = 'ProjectTest'";
        $result = $this->db->query($query);
        $nameexpected = 'ProjectTest';
        $descriptionexpected = 'This is a project test for cdash';
        $publicexpected = 1;
        $expected = array(
            'name' => $nameexpected,
            'description' => $descriptionexpected,
            'public' => $publicexpected);

        $this->assertEqual($result[0], $expected);
    }

    public function testIndexProjectTest()
    {
        $content = $this->get($this->url . '/api/v1/index.php?project=ProjectTest');
        if (strpos($content, 'CDash - ProjectTest') === false) {
            $this->fail('"CDash - ProjectTest" not found when expected');
            return 1;
        }
    }

    public function testEditProject()
    {
        $content = $this->connect($this->url);
        if (!$content) {
            return;
        }
        $this->login();
        $projectid = $this->db->query("SELECT id FROM project WHERE name = 'ProjectTest'");
        $content = $this->connect($this->url . '/createProject.php?projectid=' . $projectid[0]['id']);
        if (!$content) {
            return;
        }
//  $this->analyse($this->clickLink('Edit project'));
//  echo $this->analyse($this->setField('projectSelection','ProjectTest'));
        $description = $this->getBrowser()->getField('description');
        $public = $this->getBrowser()->getField('public');
        $descriptionExpected = 'This is a project test for cdash';
        if (strcmp($description, $descriptionExpected) != 0) {
            $this->assertEqual($description, $descriptionExpected);
            return;
        }
        if (strcmp($public, '1') != 0) {
            $this->assertEqual($public, '1');
            return;
        }
        $content = $this->analyse($this->clickLink('CTestConfig.cmake'));
        $expected = '## This file should be placed in the root directory of your project.';
        if (!$this->findString($content, $expected)) {
            $this->assertText($content, $expected);
            return;
        }
        $this->back();
        $this->post($this->getUrl(), array('Delete' => true));
        $headerExpected = "window.location='user.php'";
        $content = $this->getBrowser()->getContent();
        if ($this->findString($content, $headerExpected)) {
            $msg = "We have well been redirecting to user.php\n";
            $msg .= "after to have deleted ProjectTest\n";
            $this->assertTrue(true, $msg);
        } else {
            $msg = "We have not been redirecting to user.php\n";
            $msg .= "The deletion of ProjectTest failed\n";
            $this->assertTrue(false, $msg);
        }
    }
}
