<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';




use CDash\Database;
use CDash\Model\Label;

class SubscribeProjectShowLabelsTestCase extends KWWebTestCase
{
    protected $PDO;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance();
        $this->PDO->getPdo();
    }

    public function testSubscribeProjectShowsLabels()
    {
        // Get a build from today.
        $stmt = $this->PDO->query(
            'SELECT id, projectid FROM build ORDER BY starttime DESC LIMIT 1');
        $build_row = $stmt->fetch();
        $buildid = $build_row['id'];
        $projectid = $build_row['projectid'];

        // Add a label to this build.
        $label = new Label();
        $label->Id = 1;
        $label_text = $label->GetText();
        $stmt = $this->PDO->prepare(
            'INSERT INTO label2build (labelid, buildid) VALUES (:labelid, :buildid)');
        $this->PDO->execute($stmt, [':labelid' => 1, ':buildid' => $buildid]);

        // Make sure this label shows up on the subscribeProject page.
        $this->login();
        $this->get($this->url . "/subscribeProject.php?projectid=$projectid");
        $content = $this->getBrowser()->getContent();
        $this->assertTrue(strpos($content, $label_text) !== false);

        // Cleanup.
        $stmt = $this->PDO->prepare(
            'DELETE FROM label2build WHERE labelid = :labelid AND buildid = :buildid');
        $this->PDO->execute($stmt, [':labelid' => 1, ':buildid' => $buildid]);
    }
}
