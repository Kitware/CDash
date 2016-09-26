<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ProjectInDbTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testProjectTest4DbInDatabase()
    {
        $this->createProjectTest4Db();
        $query = "SELECT name,description,public FROM project WHERE name = 'ProjectTest4Db'";
        $result = $this->db->query($query);
        $nameexpected = 'ProjectTest4Db';
        $descriptionexpected = 'This is a project test for cdash';
        $publicexpected = 0;
        $expected = array('name' => $nameexpected,
            'description' => $descriptionexpected,
            'public' => $publicexpected);

        // For CDashPro projects should be public
        if ($this->cdashpro) {
            $expected['public'] = 1;
        }

        $this->assertEqual($result[0], $expected);
    }

    public function testProjectInBuildGroup()
    {
        $query = "SELECT id FROM project WHERE name = 'ProjectTest4Db'";
        $result = $this->db->query($query);
        $this->projecttestid = $result[0]['id'];
        $query = "SELECT name,starttime,endtime,description FROM buildgroup WHERE projectid = '" . $this->projecttestid . "' order by name desc";
        $result = $this->db->query($query);
        $expected = array('0' => array('name' => 'Nightly',
            'starttime' => '1980-01-01 00:00:00',
            'endtime' => '1980-01-01 00:00:00',
            'description' => 'Nightly builds'),
            '1' => array('name' => 'Experimental',
                'starttime' => '1980-01-01 00:00:00',
                'endtime' => '1980-01-01 00:00:00',
                'description' => 'Experimental builds'),
            '2' => array('name' => 'Continuous',
                'starttime' => '1980-01-01 00:00:00',
                'endtime' => '1980-01-01 00:00:00',
                'description' => 'Continuous builds'));
        $this->assertEqual($result, $expected);
    }

    public function testProjectInBuildGroupPosition()
    {
        $query = 'SELECT COUNT(*) FROM buildgroupposition WHERE buildgroupid IN (SELECT id FROM buildgroup WHERE projectid=';
        $query .= $this->projecttestid . ')';
        $result = $this->db->query($query);
        if (!strcmp($this->db->getType(), 'pgsql')) {
            $this->assertEqual($result[0]['count'], 3);
        } elseif (!strcmp($this->db->getType(), 'mysql')) {
            $this->assertEqual($result[0]['COUNT(*)'], 3);
        }
    }

    public function testUser2Project()
    {
        $query = 'SELECT userid, role, emailtype, emailcategory FROM user2project WHERE projectid=' . $this->projecttestid;
        $result = $this->db->query($query);
        $expected = array('userid' => 1,
            'role' => 2,
            'emailtype' => 3,
            'emailcategory' => 126);
        $this->assertEqual($result[0], $expected);
    }

    public function createProjectTest4Db()
    {
        $settings = array(
                'Name' => 'ProjectTest4Db',
                'Description' => 'This is a project test for cdash',
                'Public' => 0);
        $this->createProject($settings);
    }
}
