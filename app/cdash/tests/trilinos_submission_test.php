<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class TrilinosSubmissionTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function submitFiles($test, $trilinosOnly = false)
    {
        $dir = str_replace('\\', '/', dirname(__FILE__) . "/data/$test");

        $listfilename = $dir . '/orderedFileList.txt';

        $filenames = explode("\n", file_get_contents($listfilename));

        foreach ($filenames as $filename) {
            if (!$filename) {
                continue;
            }

            if ($trilinosOnly && strpos($filename, 'TrilinosDriver') !== false) {
                continue;
            }

            $fullname = str_replace("\r", '', $dir . '/' . $filename);

            if (!file_exists($fullname)) {
                $this->fail("file '$fullname' does not exist");
                return false;
            }

            if (preg_match('/TrilinosDriver/', $filename)) {
                $project = 'TrilinosDriver';
            } elseif (preg_match('/Trilinos/', $filename)) {
                $project = 'Trilinos';
            } else {
                $this->fail("file [$fullname] does not match project name Trilinos or TrilinosDriver");
                return false;
            }

            if (!$this->putCtestFile($fullname, ['project' => $project])) {
                $this->fail("Submission of file [$fullname] for project [$project] failed");
                return false;
            }
        }

        $this->assertTrue(true, 'Submission of all files succeeded');
        return true;
    }

    public function verifyResults()
    {
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&date=2011-07-22');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);

        // Verify three parent builds.
        $num_parents = count($buildgroup['builds']);
        if ($num_parents != 3) {
            $this->fail("Expected 3 parent builds, found $num_parents");
            return false;
        }

        // Isolate the parent build that we care about.
        $parent_build = null;
        foreach ($buildgroup['builds'] as $build) {
            if ($build['site'] === 'hut11.kitware' &&
                $build['buildname'] === 'Windows_NT-MSVC10-SERIAL_DEBUG_DEV'
            ) {
                $parent_build = $build;
            }
        }
        if (is_null($parent_build)) {
            $this->fail('Could not find expected parent build');
            return false;
        }

        if ($parent_build['numchildren'] != 36) {
            $this->fail('Expected 36 children, found ' . $parent_build['numchildren']);
            return false;
        }
        $parent_answers = array(
            'site' => 'hut11.kitware',
            'buildname' => 'Windows_NT-MSVC10-SERIAL_DEBUG_DEV',
            'uploadfilecount' => 0,
            'label' => '(36 labels)',
            'builddateelapsed' => 'Jul 22, 2011 - 11:15 EDT',
            'updatefiles' => '911431',
            'updateerror' => 0,
            'updatetime' => '18s',
            'configureerror' => 22,
            'configurewarning' => 36,
            'configuretime' => '5m 9s',
            'builderror' => 8,
            'buildwarning' => 296,
            'buildtime' => '10m 46s',
            'time' => '23m 3s',
            'testnotrun' => 95,
            'testfail' => 11,
            'testpass' => 303,
            'testtime' => '48s');
        $this->verifyBuild($parent_build, $parent_answers, 'parent');

        // Verify details about the children.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&parentid=' . $parent_build['id']);
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $builds = $buildgroup['builds'];

        if ($jsonobj['site'] != 'hut11.kitware') {
            $this->fail('Expected hut11.kitware, found ' . $jsonobj['site']);
        }

        $subproject_answers = array(
            'Amesos' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:29 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'AztecOO' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:28 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '9s',
                'builderror' => 0,
                'buildwarning' => 11,
                'buildtime' => '17s',
                'time' => '36s',
                'testnotrun' => 7,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2),

            'Belos' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:31 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'Claps' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:27 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '8s',
                'builderror' => 0,
                'buildwarning' => 0,
                'buildtime' => '3s',
                'time' => '20s',
                'testnotrun' => 1,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2),

            'CTrilinos' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:34 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'Didasko' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:34 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '12s',
                'builderror' => 0,
                'buildwarning' => 0,
                'buildtime' => '23s',
                'time' => '46s',
                'testnotrun' => 30,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2),

            'Epetra' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:24 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '10s',
                'builderror' => 0,
                'buildwarning' => 1,
                'buildtime' => '51s',
                'time' => '1m 10s',
                'testnotrun' => 29,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2),

            'EpetraExt' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:26 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'FEApp' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:37 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'FEI' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:31 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'Galeri' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:28 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'GlobiPack' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:26 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'Ifpack' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:29 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'Intrepid' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:32 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'Kokkos' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:24 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'Komplex' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:29 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'Mesquite' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:35 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '13s',
                'builderror' => 5,
                'buildwarning' => 1,
                'buildtime' => '2m 11s',
                'time' => '2m 35s',
                'notes' => 2),

            'ML' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:29 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '13s',
                'builderror' => 0,
                'buildwarning' => 13,
                'buildtime' => '39s',
                'time' => '1m 3s',
                'testnotrun' => 27,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2),

            'Moertel' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:32 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'NOX' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:32 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'OptiPack' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:27 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'Piro' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:34 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'Pliris' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:27 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '8s',
                'builderror' => 0,
                'buildwarning' => 0,
                'buildtime' => '1s',
                'time' => '19s',
                'testnotrun' => 0,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2),

            'RBGen' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:31 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'RTOp' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:23 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'Rythmos' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:33 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'Sacado' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:18 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '19s',
                'builderror' => 0,
                'buildwarning' => 267,
                'buildtime' => '4m 21s',
                'time' => '5m 4s',
                'testnotrun' => 0,
                'testfail' => 1,
                'testpass' => 270,
                'testtime' => '4s',
                'notes' => 2),

            'Shards' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:25 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '8s',
                'builderror' => 0,
                'buildwarning' => 0,
                'buildtime' => '10s',
                'time' => '29s',
                'testnotrun' => 0,
                'testfail' => 0,
                'testpass' => 3,
                'testtime' => '0s',
                'notes' => 2),

            'Stokhos' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:33 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '6s',
                'notes' => 2),

            'Stratimikos' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:31 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'Teuchos' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:16 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '13s',
                'builderror' => 3,
                'buildwarning' => 3,
                'buildtime' => '1m 27s',
                'time' => '1m 48s',
                'notes' => 2),

            'ThreadPool' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:18 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '8s',
                'builderror' => 0,
                'buildwarning' => 0,
                'buildtime' => '1s',
                'time' => '16s',
                'testnotrun' => 0,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2),

            'Thyra' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:27 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 1,
                'configurewarning' => 1,
                'configuretime' => '7s',
                'notes' => 2),

            'TrilinosCouplings' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:32 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '22s',
                'builderror' => 0,
                'buildwarning' => 0,
                'buildtime' => '4s',
                'time' => '36s',
                'testnotrun' => 0,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2),

            'TrilinosFramework' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:15 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '13s',
                'builderror' => 0,
                'buildwarning' => 0,
                'buildtime' => '1s',
                'time' => '1m 5s',
                'testnotrun' => 0,
                'testfail' => 10,
                'testpass' => 30,
                'testtime' => '44s',
                'notes' => 2),

            'Triutils' => array(
                'builddateelapsed' => 'Jul 22, 2011 - 11:26 EDT',
                'updatefiles' => '911431',
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 0,
                'configurewarning' => 1,
                'configuretime' => '8s',
                'builderror' => 0,
                'buildwarning' => 0,
                'buildtime' => '17s',
                'time' => '34s',
                'testnotrun' => 1,
                'testfail' => 0,
                'testpass' => 0,
                'testtime' => '0s',
                'notes' => 2));

        foreach ($subproject_answers as $subproject_name => $answer) {
            foreach ($builds as $index => $build) {
                $found = false;
                if ($build['label'] == $subproject_name) {
                    $found = true;
                    $this->verifyBuild($build, $answer, $subproject_name);
                    unset($builds[$index]);
                    break;
                }
            }
            if (!$found) {
                $this->fail("Did not find $subproject_name in the list of child builds");
            }
        }
    }

    public function verifyBuild($build, $answer, $name)
    {
        // Flatten the build array so its indices match the answer key.
        // We also check here to make sure that this build doesn't have
        // extra data that it shouldn't.
        if (array_key_exists('update', $build)) {
            if (!array_key_exists('updatetime', $answer)) {
                $this->fail("$name has update data when it should not");
            }
            $build['updatefiles'] = $build['update']['files'];
            $build['updateerror'] = $build['update']['errors'];
            $build['updatetime'] = trim($build['update']['time']);
            unset($build['update']);
        }
        if (array_key_exists('configure', $build)) {
            if (!array_key_exists('configuretime', $answer)) {
                $this->fail("$name has configure data when it should not");
            }
            $build['configureerror'] = $build['configure']['error'];
            $build['configurewarning'] = $build['configure']['warning'];
            $build['configuretime'] = trim($build['configure']['time']);
            unset($build['configure']);
        }
        if (array_key_exists('compilation', $build)) {
            if (!array_key_exists('buildtime', $answer)) {
                $this->fail("$name has compilation data when it should not");
            }
            $build['builderror'] = $build['compilation']['error'];
            $build['buildwarning'] = $build['compilation']['warning'];
            $build['buildtime'] = trim($build['compilation']['time']);
            unset($build['compilation']);
        }
        if (array_key_exists('test', $build)) {
            if (!array_key_exists('testtime', $answer)) {
                $this->fail("$name has test data when it should not");
            }
            $build['testnotrun'] = $build['test']['notrun'];
            $build['testfail'] = $build['test']['fail'];
            $build['testpass'] = $build['test']['pass'];
            $build['testtime'] = trim($build['test']['time']);
        }

        // Now check that all the values are what we expect.
        foreach ($answer as $key => $value) {
            if (!array_key_exists($key, $build)) {
                $this->fail("$name is missing $key");
            } elseif ($build[$key] != $value) {
                $this->fail("Expected $value but found " . $build[$key] . " for $name::$key");
            }
        }
    }
}
