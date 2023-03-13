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
    protected $BuildId;

    public function __construct()
    {
        parent::__construct();
        $this->BuildId = null;
    }

    public function testUniqueDiffs()
    {
        // Find the highest buildid created so far and add one to it.
        // This should give us a buildid that doesn't exist yet.
        $pdo = \CDash\Database::getInstance();
        $stmt = $pdo->query('SELECT id FROM build ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();
        $buildid = $row['id'];
        $buildid++;

        // Verify that this buildid does not exist yet.
        $safe_build = false;
        while (!$safe_build) {
            $stmt = $pdo->prepare('SELECT id FROM build WHERE id=?');
            $stmt->execute([$buildid]);
            $row = $stmt->fetch();
            if (!$row) {
                $this->BuildId = $buildid;
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
        $stmt->execute([$this->BuildId, 0, 1, -1]);
        try {
            $stmt->execute([$this->BuildId, 0, 2, -2]);
        } catch (PDOException $exception) {
            $this->checkIntegrityViolation($stmt);
        }

        $this->checkRowCount($pdo, 'builderrordiff', 1);

        // configureerrordiff
        $stmt = $pdo->prepare(
            'INSERT INTO configureerrordiff (buildid, type, difference)
            VALUES (?, ?, ?)');
        $stmt->execute([$this->BuildId, 0, -1]);
        try {
            $stmt->execute([$this->BuildId, 0, -2]);
        } catch (PDOException $exception) {
            $this->checkIntegrityViolation($stmt);
        }

        $this->checkRowCount($pdo, 'configureerrordiff', 1);

        // testdiff
        $stmt = $pdo->prepare(
            'INSERT INTO testdiff
            (buildid, type, difference_positive, difference_negative)
            VALUES (?, ?, ?, ?)');
        $stmt->execute([$this->BuildId, 0, 1, -1]);
        try {
            $stmt->execute([$this->BuildId, 0, 2, -2]);
        } catch (PDOException $exception) {
            $this->checkIntegrityViolation($stmt);
        }

        $this->checkRowCount($pdo, 'testdiff', 1);

        // Cleanup
        $stmt = $pdo->prepare('DELETE FROM builderrordiff WHERE buildid=?');
        $stmt->execute([$this->BuildId]);
        $stmt = $pdo->prepare('DELETE FROM configureerrordiff WHERE buildid=?');
        $stmt->execute([$this->BuildId]);
        $stmt = $pdo->prepare('DELETE FROM testdiff WHERE buildid=?');
        $stmt->execute([$this->BuildId]);
    }

    public function testUniqueDiffsUpgrade()
    {
        require_once 'include/upgrade_functions.php';

        $pdo = get_link_identifier()->getPdo();
        $tables = ['test_builderrordiff', 'test_configureerrordiff', 'test_testdiff'];

        foreach ($tables as $table) {
            // Create testing tables.
            if (config('database.default') == 'pgsql') {
                $create_query = '
                    CREATE TABLE "' . $table . '" (
                            "buildid" integer NOT NULL,
                            "type" smallint NOT NULL,
                            "difference" integer NOT NULL
                            )';
            } else {
                // MySQL
                $create_query = "
                    CREATE TABLE `$table` (
                            `buildid` int(11) NOT NULL,
                            `type` tinyint(4) NOT NULL,
                            `difference` int(11) NOT NULL,
                            KEY `buildid` (`buildid`),
                            KEY `type` (`type`)
                            )";
            }
            if (!$pdo->query($create_query)) {
                $this->fail("Error creating $table");
            }

            // Insert duplicate data into each.
            $stmt = $pdo->prepare(
                "INSERT INTO $table (buildid, type, difference)
                VALUES (?, 0, 1)");
            $stmt->execute([$this->BuildId]);
            $stmt = $pdo->prepare(
                "INSERT INTO $table (buildid, type, difference)
                VALUES (?, 0, 2)");
            $stmt->execute([$this->BuildId]);

            // Verify duplicate was inserted successfully.
            $this->checkRowCount($pdo, $table, 2);
        }

        // Run the upgrade function.
        AddUniqueConstraintToDiffTables(true);

        foreach ($tables as $table) {
            // Verify that each table only has one row.
            $this->checkRowCount($pdo, $table, 1);

            // Drop the testing tables.
            $pdo->query("DROP TABLE $table");
        }
    }

    private function checkRowCount($pdo, $table, $expected)
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) c FROM $table WHERE buildid=? AND type=0");
        $stmt->execute([$this->BuildId]);
        $row = $stmt->fetch();
        $num_rows = $row['c'];
        if ($num_rows != $expected) {
            $this->fail("Expected $expected row(s) for $table, found $num_rows");
        }
    }

    private function checkIntegrityViolation($stmt)
    {
        // Make sure our INSERT statement failed the way we expect it to.
        // MySQL returns 23000 for an integrity constraint violation,
        // while PostGres has a code specifically for unique violations (23505).
        //
        // Because of this difference, we only verify that the code belongs to
        // class #23.
        $errorClass = (int) ($stmt->errorCode() / 1000);
        if ($errorClass !== 23) {
            $this->fail("Expected error class 23, found $errorClass");
        }
    }
}
