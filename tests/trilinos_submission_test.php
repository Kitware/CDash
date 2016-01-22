<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class TrilinosSubmissionTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function submitFiles($trilinosOnly=false)
    {
        $dir = str_replace("\\", '/',
      dirname(__FILE__).'/data/ActualTrilinosSubmission');

        $listfilename = $dir."/orderedFileList.txt";

        $filenames = explode("\n", file_get_contents($listfilename));

        foreach ($filenames as $filename) {
            if (!$filename) {
                continue;
            }

            if ($trilinosOnly && strpos($filename, "TrilinosDriver") !== false) {
                continue;
            }

            $fullname = str_replace("\r", '', $dir.'/'.$filename);

            if (!file_exists($fullname)) {
                $this->fail("file '$fullname' does not exist");
                return false;
            }

            if (preg_match("/TrilinosDriver/", $filename)) {
                $project = "TrilinosDriver";
            } elseif (preg_match("/Trilinos/", $filename)) {
                $project = "Trilinos";
            } else {
                $this->fail("file [$fullname] does not match project name Trilinos or TrilinosDriver");
                return false;
            }

            if (!$this->submission($project, $fullname)) {
                $this->fail("Submission of file [$fullname] for project [$project] failed");
                return false;
            }
        }

        $this->assertTrue(true, "Submission of all files succeeded");
        return true;
    }

    public function verifyResults()
    {
        $this->get($this->url . "/api/v1/index.php?project=Trilinos&date=2011-07-22");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);

        // Verify only one parent build.
        $num_parents = count($buildgroup['builds']);
        if ($num_parents != 1) {
            $this->fail("Expected 1 parent build, found $num_parents");
            return false;
        }

        // Verify details about the parent build.
        $parent_build = $buildgroup['builds'][0];
        if ($parent_build['numchildren'] != 36) {
            $this->fail("Expected 36 children, found " . $parent_build['numchildren']);
            return false;
        }
        $parent_answers = array(
                'site' => 'hut11.kitware',
                'buildname' => 'Windows_NT-MSVC10-SERIAL_DEBUG_DEV',
                'uploadfilecount' => 0,
                'label' => '(36 labels)',
                'builddateelapsed' => 'Jul 22, 2011 - 11:15 EDT',
                'updatefiles' => 15,
                'updateerror' => 0,
                'updatetime' => '18s',
                'configureerror' => 22,
                'configurewarning' => 73,
                'configuretime' => '5m 9s',
                'builderror' => 8,
                'buildwarning' => 296,
                'buildtime' => '23m',
                'testnotrun' => 95,
                'testfail' => 11,
                'testpass' => 303,
                'testtime' => '48s');
        $this->verifyBuild($parent_build, $parent_answers, "parent");

        // Verify details about the children.
        $this->get($this->url . "/api/v1/index.php?project=Trilinos&parentid=" . $parent_build['id']);
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $builds = $buildgroup['builds'];

        $subproject_answers = array(
                "Amesos" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:29 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "AztecOO" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:28 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '9s',
                        'builderror' => 0,
                        'buildwarning' => 11,
                        'buildtime' => '54s',
                        'testnotrun' => 7,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1),

                "Belos" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:31 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "Claps" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:27 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '8s',
                        'builderror' => 0,
                        'buildwarning' => 0,
                        'buildtime' => '18s',
                        'testnotrun' => 1,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1),

                "CTrilinos" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:34 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "Didasko" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:34 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '12s',
                        'builderror' => 0,
                        'buildwarning' => 0,
                        'buildtime' => '1m 24s',
                        'testnotrun' => 30,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1),

                "Epetra" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:24 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '10s',
                        'builderror' => 0,
                        'buildwarning' => 1,
                        'buildtime' => '1m 30s',
                        'testnotrun' => 29,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1),

                "EpetraExt" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:26 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "FEApp" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:37 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "FEI" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:31 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "Galeri" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:28 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "GlobiPack" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:26 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "Ifpack" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:29 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "Intrepid" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:32 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "Kokkos" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:24 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "Komplex" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:29 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "Mesquite" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:35 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '13s',
                        'builderror' => 5,
                        'buildwarning' => 1,
                        'buildtime' => '2m 54s',
                        'note' => 1),

                "ML" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:29 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '13s',
                        'builderror' => 0,
                        'buildwarning' => 13,
                        'buildtime' => '1m 6s',
                        'testnotrun' => 27,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1),

                "Moertel" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:32 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "NOX" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:32 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "OptiPack" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:27 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "Piro" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:34 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "Pliris" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:27 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '8s',
                        'builderror' => 0,
                        'buildwarning' => 0,
                        'buildtime' => '1m',
                        'testnotrun' => 0,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1),

                "RBGen" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:31 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "RTOp" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:23 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "Rythmos" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:33 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "Sacado" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:18 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '19s',
                        'builderror' => 0,
                        'buildwarning' => 267,
                        'buildtime' => '5m',
                        'testnotrun' => 0,
                        'testfail' => 1,
                        'testpass' => 270,
                        'testtime' => '4s',
                        'note' => 1),

                "Shards" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:25 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '8s',
                        'builderror' => 0,
                        'buildwarning' => 0,
                        'buildtime' => '1m',
                        'testnotrun' => 0,
                        'testfail' => 0,
                        'testpass' => 3,
                        'testtime' => '0s',
                        'note' => 1),

                "Stokhos" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:33 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '6s',
                        'note' => 1),

                "Stratimikos" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:31 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "Teuchos" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:16 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '13s',
                        'builderror' => 3,
                        'buildwarning' => 3,
                        'buildtime' => '1m 48s',
                        'note' => 1),

                "ThreadPool" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:18 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '8s',
                        'builderror' => 0,
                        'buildwarning' => 0,
                        'buildtime' => '48s',
                        'testnotrun' => 0,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1),

                "Thyra" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:27 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 1,
                        'configurewarning' => 2,
                        'configuretime' => '7s',
                        'note' => 1),

                "TrilinosCouplings" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:32 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '22s',
                        'builderror' => 0,
                        'buildwarning' => 0,
                        'buildtime' => '36s',
                        'testnotrun' => 0,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1),

                "TrilinosFramework" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:15 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 3,
                        'configuretime' => '13s',
                        'builderror' => 0,
                        'buildwarning' => 0,
                        'buildtime' => '54s',
                        'testnotrun' => 0,
                        'testfail' => 10,
                        'testpass' => 30,
                        'testtime' => '44s',
                        'note' => 1),

                "Triutils" => array(
                        'builddateelapsed' => 'Jul 22, 2011 - 11:26 EDT',
                        'updatefiles' => 15,
                        'updateerror' => 0,
                        'updatetime' => '18s',
                        'configureerror' => 0,
                        'configurewarning' => 2,
                        'configuretime' => '8s',
                        'builderror' => 0,
                        'buildwarning' => 0,
                        'buildtime' => '48s',
                        'testnotrun' => 1,
                        'testfail' => 0,
                        'testpass' => 0,
                        'testtime' => '0s',
                        'note' => 1));

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

        // Also verify that all the child builds have two notes.
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
