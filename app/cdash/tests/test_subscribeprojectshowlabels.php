<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';




use CDash\Database;
use CDash\Model\Label;
use Illuminate\Support\Facades\DB;

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
        DB::insert('INSERT INTO label2build (labelid, buildid) VALUES (?, ?)', [1, $buildid]);

        // Make sure this label shows up on the subscribeProject page.
        $this->login();
        $this->get($this->url . "/subscribeProject.php?projectid=$projectid");
        $content = $this->getBrowser()->getContent();
        $this->assertTrue(str_contains($content, $label_text));

        // Cleanup.
        DB::delete('DELETE FROM label2build WHERE labelid = ? AND buildid = ?', [1, $buildid]);
    }
}
