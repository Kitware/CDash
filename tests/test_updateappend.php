<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class UpdateAppendTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->OriginalConfigSettings = '';
    }

    public function testUpdateAppend()
    {
        echo "1. testUpdateAppend\n";

        // Submit our test data.
        $rep = dirname(__FILE__) . '/data/UpdateAppend';
        if (!$this->submission('EmailProjectExample', "$rep/Update_1.xml")) {
            $this->fail('failed to submit Update_1.xml');
            return 1;
        }
        if (!$this->submission('EmailProjectExample', "$rep/Update_2.xml")) {
            $this->fail('failed to submit Update_2.xml');
            return 1;
        }
        if (!$this->submission('EmailProjectExample', "$rep/Update_3.xml")) {
            $this->fail('failed to submit Update_3.xml');
            return 1;
        }

        // Get the buildid that we just created so we can delete it later.
        $buildids = array();
        $buildid_results = pdo_query(
            "SELECT id FROM build WHERE name='test_updateappend'");
        while ($buildid_array = pdo_fetch_array($buildid_results)) {
            $buildids[] = $buildid_array['id'];
        }

        if (count($buildids) != 1) {
            foreach ($buildids as $id) {
                remove_build($id);
            }
            $this->fail('Expected 1 build, found ' . count($buildids));
            return 1;
        }
        $buildid = $buildids[0];

        // Get the updateid associated with the build id
        $query = pdo_query('SELECT updateid FROM build2update WHERE buildid=' . qnum($buildid));
        $query_array = pdo_fetch_array($query);
        $updateid = $query_array['updateid'];
        $build_query = pdo_query('SELECT * FROM buildupdate WHERE id=' . qnum($updateid));
        $buildupdate_array = pdo_fetch_array($build_query);

        // Check that values have been updated correctly
        // If any of these checks fail, the build still needs to be deleted
        try {
            $success = true;

            if ($buildupdate_array['nfiles'] != 3) {
                throw new Exception('Expected nfiles=3, found nfiles= ' . qnum($buildupdate_array['nfiles']));
            }
            if ($buildupdate_array['warnings'] != 1) {
                throw new Exception('Expected warnings=1, found warnings= ' . qnum($buildupdate_array['nwarnings']));
            }

            // Note: UpdateType is only read from the first Update
            if ($buildupdate_array['type'] != 'GIT') {
                throw new Exception('Expected type=GIT, found type= ' . $buildupdate_array['type']);
            }

            // Note: Commands from all Updates are concatenated together
            if ($buildupdate_array['command'] != 'Command 1Command 2Command 3') {
                throw new Exception('Expected command=Command 1Command 2Command 3, found command= ' . $buildupdate_array['command']);
            }

            // Note: MDT = GMT - 6
            if ($buildupdate_array['starttime'] != '2015-08-24 04:04:14') {
                throw new Exception('Expected starttime=GIT, found starttime= ' . $buildupdate_array['starttime']);
            }
            if ($buildupdate_array['endtime'] != '2015-08-24 04:20:30') {
                throw new Exception('Expected endtime=2015-08-24 04:20:30, found endtime= ' . $buildupdate_array['endtime']);
            }

            // Note: UpdateReturnStatus is overwritten with each update
            if ($buildupdate_array['status'] != '') {
                throw new Exception("Expected status='', found status= " . $buildupdate_array['status']);
            }

            // Note: Revision and PriorRevision are only read from the first Update AND
            //       only IF they're under the <Update> tag (not in <Updated> or <Revisions>)
            if ($buildupdate_array['revision'] != 3) {
                throw new Exception('Expected revision=3, found revision= ' . $buildupdate_array['revision']);
            }
            if ($buildupdate_array['priorrevision'] != 2) {
                throw new Exception('Expected priorrevision=2, found priorrevision= ' . $buildupdate_array['priorrevision']);
            }

            // Note: Path is only read from the first Update
            if ($buildupdate_array['path'] != 'mypath') {
                throw new Exception('Expected path=mypath, found path= ' . $buildupdate_array['path']);
            }
        } catch (Exception $e) {
            $success = false;
            $error_message = $e->getMessage();
        }

        // Delete the build
        remove_build($buildid);

        if ($success) {
            $this->pass('Test passed');
            return 0;
        } else {
            $this->fail($error_message);
            return 1;
        }
    }
}
