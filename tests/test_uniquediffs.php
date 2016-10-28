<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class UniqueDiffsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testUniqueDiffs()
    {
        // Find the highest buildid created so far.
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare('SELECT id FROM build ORDER BY id DESC LIMIT 1');
        $stmt->execute();
        $row = $stmt->fetch();
        $buildid = $row['id'];

        // Add one to it.  This should give us a buildid that doesn't exist yet.
        $buildid++;
        // Make sure we have a buildid that does not exist yet.
        $safe_build = false;
        while (!$safe_build) {
            $stmt = $pdo->prepare('SELECT id FROM build WHERE id=?');
            $stmt->execute([$buildid]);
            $row = $stmt->fetch();
            if (!$row) {
                $safe_build = true;
            } else {
                $buildid++;
            }
        }

        // Perform multiple INSERTs that violate the unique constraint
        // and verify that only one row is recorded.
        //
        // builderrordiff
        $stmt = $pdo->prepare(
            'INSERT INTO builderrordiff
            (buildid, type, difference_positive, difference_negative)
            VALUES (?, ?, ?, ?)');
        $stmt->execute([$buildid, 0, 1, -1]);
        $stmt->execute([$buildid, 0, 2, -2]);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) c FROM builderrordiff WHERE buildid=?');
        $stmt->execute([$buildid]);
        $row = $stmt->fetch();
        $num_rows = $row['c'];
        if ($num_rows != 1) {
            $this->fail("Expected 1 row for builderrordiff, found $num_rows");
        }
        // configureerrordiff
        $stmt = $pdo->prepare(
            'INSERT INTO configureerrordiff (buildid, type, difference)
            VALUES (?, ?, ?)');
        $stmt->execute([$buildid, 0, -1]);
        $stmt->execute([$buildid, 0, -2]);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) c FROM configureerrordiff WHERE buildid=?');
        $stmt->execute([$buildid]);
        $row = $stmt->fetch();
        $num_rows = $row['c'];
        if ($num_rows != 1) {
            $this->fail("Expected 1 row for configureerrordiff, found $num_rows");
        }
        // testdiff
        $stmt = $pdo->prepare(
            'INSERT INTO testdiff
            (buildid, type, difference_positive, difference_negative)
            VALUES (?, ?, ?, ?)');
        $stmt->execute([$buildid, 0, 1, -1]);
        $stmt->execute([$buildid, 0, 2, -2]);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) c FROM testdiff WHERE buildid=?');
        $stmt->execute([$buildid]);
        $row = $stmt->fetch();
        $num_rows = $row['c'];
        if ($num_rows != 1) {
            $this->fail("Expected 1 row for testdiff, found $num_rows");
        }

        // Cleanup
        $stmt = $pdo->prepare('DELETE FROM builderrordiff WHERE buildid=?');
        $stmt->execute([$buildid]);
        $stmt = $pdo->prepare('DELETE FROM configureerrordiff WHERE buildid=?');
        $stmt->execute([$buildid]);
        $stmt = $pdo->prepare('DELETE FROM testdiff WHERE buildid=?');
        $stmt->execute([$buildid]);
    }
}
